<?php

namespace App\Domain\Records\Models;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Observers\RecordObserver;
use App\Domain\Records\QueryBuilder\RecordBuilder;
use App\Domain\Records\Resources\RecordResource;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[UseResource(RecordResource::class)]
#[UseEloquentBuilder(RecordBuilder::class)]
#[ObservedBy([RecordObserver::class])]
class Record extends Authenticatable
{
    use HasUlids;

    protected $guarded = [];

    public $timestamps = true;

    public ?Collection $collection = null;

    private static bool $allowDirectInstantiation = false;

    protected $hidden = [];

    public array $expandedRelations = [];

    public function __construct(array $attributes = [])
    {
        if (! static::$allowDirectInstantiation) {
            throw new RuntimeException('Record must be instantiated using Record::of($collection).');
        }

        parent::__construct($attributes);
    }

    /**
     * Create a new Record instance straight from the table without collection metadata.
     */
    public static function fromTable(string $tableName): self
    {
        static::$allowDirectInstantiation = true;
        $instance = new self;
        static::$allowDirectInstantiation = false;

        $instance->setTable($tableName);

        return $instance;
    }

    /**
     * Create a new Record instance for a specific collection
     */
    public static function of(Collection $collection): self
    {
        static::$allowDirectInstantiation = true;
        $instance = new self;
        static::$allowDirectInstantiation = false;

        $instance->collection = $collection;
        $instance->setTable($collection->getPhysicalTableName());

        $instance->casts = $collection->getCachedCasts();

        if ($collection->type === CollectionType::Auth) {
            $instance->hidden = ['password'];
        }

        return $instance;
    }

    /**
     * Override newInstance so Laravel's query builder hydrates records through
     * of($collection) rather than calling `new static` directly.
     */
    public function newInstance($attributes = [], $exists = false): static
    {
        if ($this->collection === null) {
            throw new RuntimeException('Record must be instantiated using Record::of($collection)');
        }

        $model = static::of($this->collection);
        $model->exists = $exists;
        $model->setConnection($this->getConnectionName());
        $model->setTable($this->getTable());
        $model->mergeCasts($this->casts);
        $model->hidden = $this->hidden;
        $model->fill((array) $attributes);

        return $model;
    }

    /**
     * Get the table name for this record instance
     */
    public function getTable(): string
    {
        return $this->table ?? parent::getTable();
    }

    public function isSuperuser(): bool
    {
        return $this->getTable() === 'superusers';
    }

    public function toArray(): array
    {
        $data = parent::toArray();

        if (! $this->collection instanceof Collection) {
            return $data;
        }

        foreach ($this->collection->fields ?? [] as $field) {
            if (($field['type'] ?? null) !== CollectionFieldType::File->value) {
                continue;
            }

            $fieldName = (string) ($field['name'] ?? '');
            if ($fieldName === '' || ! array_key_exists($fieldName, $data)) {
                continue;
            }

            $isMultiple = (bool) ($field['multiple'] ?? false);
            $isProtected = (bool) ($field['protected'] ?? false);
            $data[$fieldName] = $this->normalizeFileOutputValue($data[$fieldName], $isMultiple, $isProtected, $fieldName);
        }

        return $data;
    }

    private function normalizeFileOutputValue(mixed $value, bool $isMultiple, bool $isProtected, string $fieldName): mixed
    {
        $list = $this->normalizeFileMetadataList($value, $isProtected, $fieldName);

        if ($isMultiple) {
            return $list;
        }

        return $list[0] ?? null;
    }

    /**
     * @return array<int, array{name: string, path: string, size: int, extension: string, mime: string, protected: bool, url: ?string}>
     */
    private function normalizeFileMetadataList(mixed $value, bool $isProtected, string $fieldName): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                return [];
            }

            $value = $decoded;
        }

        if (! is_array($value)) {
            return [];
        }

        if (! array_is_list($value)) {
            $value = [$value];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => $this->normalizeFileMetadata($item, $isProtected, $fieldName))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{name: string, path: string, size: int, extension: string, mime: string, protected: bool, url: ?string}
     */
    private function normalizeFileMetadata(array $metadata, bool $isProtected, string $fieldName): array
    {
        $path = trim((string) ($metadata['path'] ?? ''));
        $name = trim((string) ($metadata['name'] ?? ''));

        if ($name === '' && $path !== '') {
            $name = basename($path);
        }

        $extension = trim((string) ($metadata['extension'] ?? ''));
        if ($extension === '') {
            $extension = (string) pathinfo($name !== '' ? $name : $path, PATHINFO_EXTENSION);
        }

        $size = max(0, (int) ($metadata['size'] ?? 0));
        $mime = trim((string) ($metadata['mime'] ?? 'application/octet-stream'));

        return [
            'name' => $name,
            'path' => $path,
            'size' => $size,
            'extension' => strtolower($extension),
            'mime' => $mime === '' ? 'application/octet-stream' : $mime,
            'protected' => $isProtected,
            'url' => $this->resolveFileMetadataUrl($path, $isProtected, $fieldName),
        ];
    }

    private function resolveFileMetadataUrl(string $path, bool $isProtected, string $fieldName): ?string
    {
        if ($path === '') {
            return null;
        }

        if ($isProtected && $this->collection instanceof Collection && $this->exists) {
            return route('records.files.show', [
                'collection' => $this->collection->id,
                'record' => (string) $this->getKey(),
                'field' => $fieldName,
                'path' => $path,
            ]);
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        try {
            return Storage::temporaryUrl($path, now()->addMinutes(15));
        } catch (\Throwable) {
            //
        }

        try {
            return Storage::url($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
