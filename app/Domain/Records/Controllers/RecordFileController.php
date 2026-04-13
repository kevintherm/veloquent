<?php

namespace App\Domain\Records\Controllers;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Actions\ShowRecordAction;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecordFileController extends ApiController
{
    public function __construct(private readonly ShowRecordAction $showRecordAction) {}

    public function show(Request $request, Collection $collection, string $recordId, string $field): BinaryFileResponse
    {
        $record = $this->showRecordAction->execute($collection, $recordId);

        $fieldDefinition = collect($collection->fields ?? [])->first(function (mixed $item) use ($field): bool {
            if (! is_array($item) && ! $item instanceof \ArrayAccess) {
                return false;
            }

            $name = $item['name'] ?? null;
            $type = $item['type'] ?? null;
            $isProtected = (bool) ($item['protected'] ?? false);

            return ($name === $field)
                && ($type === CollectionFieldType::File->value)
                && $isProtected;
        });

        abort_if($fieldDefinition === null, 404);

        $path = trim((string) $request->query('path', ''));
        abort_if($path === '', 404);

        $metadata = $this->normalizeFileMetadataList($record->getAttribute($field));

        $matched = collect($metadata)->first(function (array $file) use ($path): bool {
            return (string) ($file['path'] ?? '') === $path;
        });

        abort_if(! is_array($matched), 404);

        $disk = Storage::disk((string) config('filesystems.default', 'local'));
        abort_if(! $disk->exists($path), 404);

        $absolutePath = $disk->path($path);
        abort_if(! is_string($absolutePath) || $absolutePath === '' || ! is_file($absolutePath), 404);

        $mime = trim((string) ($matched['mime'] ?? ''));
        $headers = [];

        if ($mime !== '') {
            $headers['Content-Type'] = $mime;
        }

        return response()->file($absolutePath, $headers);
    }

    /**
     * @return array<int, array{name: string, path: string, size: int, extension: string, mime: string}>
     */
    private function normalizeFileMetadataList(mixed $value): array
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
            ->map(fn (array $item): array => $this->normalizeFileMetadata($item))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{name: string, path: string, size: int, extension: string, mime: string}
     */
    private function normalizeFileMetadata(array $metadata): array
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
        ];
    }
}
