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

    public function __construct(array $attributes = [])
    {
        if (! static::$allowDirectInstantiation) {
            throw new \RuntimeException('Record must be instantiated using Record::of($collection)');
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

        $casts = [];
        foreach ($collection->fields ?? [] as $field) {
            $fieldName = $field['name'];

            if ($fieldName == 'password' && $collection->type === CollectionType::Auth) {
                $casts[$fieldName] = 'hashed';

                continue;
            }

            $cast = CollectionFieldType::tryFrom($field['type'])?->eloquentCast();
            if ($cast !== null) {
                $casts[$fieldName] = $cast;
            }
        }

        $instance->casts = $casts;

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
            throw new \RuntimeException('Record must be instantiated using Record::of($collection)');
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
}
