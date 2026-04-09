<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileFieldProcessor
{
    /**
     * Requires request object.
     *
     * @return array{data: array<string, mixed>, stored_paths: array<int, string>, pending_delete_paths: array<int, string>}
     */
    public function processForCreate(Collection $collection, array $data, ?Request $request = null): array
    {
        $request ??= request();
        $fileFields = $this->resolveFileFieldConfigurations($collection);

        if ($fileFields === []) {
            return [
                'data' => $data,
                'stored_paths' => [],
                'pending_delete_paths' => [],
            ];
        }

        $errors = [];
        $storedPaths = [];

        foreach ($fileFields as $fieldName => $fieldConfig) {
            $appendKey = $fieldName.'+';
            $removeKey = $fieldName.'-';

            if ($this->operationProvided($request, $data, $appendKey)) {
                $this->addError($errors, $appendKey, 'Append operations are only supported during updates.');
            }

            if ($this->operationProvided($request, $data, $removeKey)) {
                $this->addError($errors, $removeKey, 'Remove operations are only supported during updates.');
            }

            $replacement = [];

            if ($this->operationProvided($request, $data, $fieldName)) {
                $replacement = $this->buildStoredMetadataForOperation(
                    $collection,
                    $request,
                    $data,
                    $fieldName,
                    $fieldConfig,
                    $errors,
                    $storedPaths,
                );
            }

            $this->assertCountConstraints($fieldName, $replacement, $fieldConfig, $errors);
            $data[$fieldName] = $replacement;
        }

        if ($errors !== []) {
            $this->deletePaths($storedPaths);

            throw ValidationException::withMessages($errors);
        }

        return [
            'data' => $data,
            'stored_paths' => array_values(array_unique($storedPaths)),
            'pending_delete_paths' => [],
        ];
    }

    /**
     * @return array{data: array<string, mixed>, stored_paths: array<int, string>, pending_delete_paths: array<int, string>}
     */
    public function processForUpdate(Collection $collection, Record $record, array $data, ?Request $request = null): array
    {
        $request ??= request();
        $fileFields = $this->resolveFileFieldConfigurations($collection);

        if ($fileFields === []) {
            return [
                'data' => $data,
                'stored_paths' => [],
                'pending_delete_paths' => [],
            ];
        }

        $errors = [];
        $storedPaths = [];
        $pendingDeletePaths = [];

        foreach ($fileFields as $fieldName => $fieldConfig) {
            $appendKey = $fieldName.'+';
            $removeKey = $fieldName.'-';

            $hasReplace = $this->operationProvided($request, $data, $fieldName);
            $hasAppend = $this->operationProvided($request, $data, $appendKey);
            $hasRemove = $this->operationProvided($request, $data, $removeKey);

            if (! $hasReplace && ! $hasAppend && ! $hasRemove) {
                continue;
            }

            if (! ($fieldConfig['multiple'] ?? false) && ($hasAppend || $hasRemove)) {
                $this->addError($errors, $fieldName, 'Append and remove operations are only supported when multiple is enabled.');

                continue;
            }

            if ($hasReplace && ($hasAppend || $hasRemove)) {
                $this->addError($errors, $fieldName, 'Replace cannot be combined with append or remove in the same request.');

                continue;
            }

            $existing = $this->normalizeStoredMetadata($record->getAttribute($fieldName));
            $final = $existing;

            if ($hasReplace) {
                $replacement = $this->buildStoredMetadataForOperation(
                    $collection,
                    $request,
                    $data,
                    $fieldName,
                    $fieldConfig,
                    $errors,
                    $storedPaths,
                );

                $final = $replacement;
                $pendingDeletePaths = [...$pendingDeletePaths, ...$this->extractPaths($existing)];
            } else {
                if ($hasAppend) {
                    $appended = $this->buildStoredMetadataForOperation(
                        $collection,
                        $request,
                        $data,
                        $appendKey,
                        $fieldConfig,
                        $errors,
                        $storedPaths,
                    );

                    $final = [...$final, ...$appended];
                }

                if ($hasRemove) {
                    $selectors = $this->normalizeRemovalSelectors(
                        $this->rawInputForKey($request, $data, $removeKey),
                        $removeKey,
                        $errors,
                    );

                    $split = $this->splitByRemovalSelectors($final, $selectors);
                    $final = $split['remaining'];
                    $pendingDeletePaths = [...$pendingDeletePaths, ...$this->extractPaths($split['removed'])];
                }
            }

            $this->assertCountConstraints($fieldName, $final, $fieldConfig, $errors);
            $data[$fieldName] = array_values($final);

            unset($data[$appendKey], $data[$removeKey]);
        }

        if ($errors !== []) {
            $this->deletePaths($storedPaths);

            throw ValidationException::withMessages($errors);
        }

        return [
            'data' => $data,
            'stored_paths' => array_values(array_unique($storedPaths)),
            'pending_delete_paths' => array_values(array_unique($pendingDeletePaths)),
        ];
    }

    public function cleanupRecordFiles(Record $record): void
    {
        if (! $record->collection instanceof Collection) {
            return;
        }

        $fileFields = $this->resolveFileFieldConfigurations($record->collection);
        if ($fileFields === []) {
            return;
        }

        $paths = [];

        foreach ($fileFields as $fieldName => $fieldConfig) {
            $value = $record->getAttribute($fieldName);
            $normalized = $this->normalizeStoredMetadata($value);
            $paths = [...$paths, ...$this->extractPaths($normalized)];
        }

        $this->deletePaths($paths);
    }

    /**
     * @param  array<int, string>  $paths
     */
    public function deletePaths(array $paths): void
    {
        $disk = Storage::disk($this->resolveDisk());

        foreach (array_values(array_unique($paths)) as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            try {
                $disk->delete($path);
            } catch (\Throwable) {
                //
            }
        }
    }

    /**
     * @return array<string, array{multiple: bool, nullable: bool, min: ?int, max: ?int, max_size_kb: ?int, allowed_mime_types: array<int, string>}>
     */
    private function resolveFileFieldConfigurations(Collection $collection): array
    {
        $config = [];

        foreach ($collection->fields ?? [] as $field) {
            if (($field['type'] ?? null) !== CollectionFieldType::File->value) {
                continue;
            }

            $fieldName = (string) ($field['name'] ?? '');
            if ($fieldName === '') {
                continue;
            }

            $config[$fieldName] = [
                'multiple' => (bool) ($field['multiple'] ?? false),
                'nullable' => (bool) ($field['nullable'] ?? false),
                'min' => $this->toNullableInt($field['min'] ?? null),
                'max' => $this->toNullableInt($field['max'] ?? null),
                'max_size_kb' => $this->toNullableInt($field['max_size_kb'] ?? null),
                'allowed_mime_types' => $this->normalizeAllowedMimeTypes((array) ($field['allowed_mime_types'] ?? [])),
            ];
        }

        return $config;
    }

    private function operationProvided(Request $request, array $data, string $key): bool
    {
        $requestPayload = $request->all();

        return array_key_exists($key, $data)
            || array_key_exists($key, $requestPayload)
            || $request->hasFile($key);
    }

    private function rawInputForKey(Request $request, array $data, string $key): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        return $request->input($key);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{multiple: bool, nullable: bool, min: ?int, max: ?int, max_size_kb: ?int, allowed_mime_types: array<int, string>}  $fieldConfig
     * @param  array<string, array<int, string>>  $errors
     * @param  array<int, string>  $storedPaths
     * @return array<int, array{name: string, path: string, size: int, extension: string, mime: string}>
     */
    private function buildStoredMetadataForOperation(
        Collection $collection,
        Request $request,
        array $data,
        string $key,
        array $fieldConfig,
        array &$errors,
        array &$storedPaths,
    ): array {
        $metadataFromInput = $this->normalizeInputMetadata(
            $this->rawInputForKey($request, $data, $key),
            $key,
            $fieldConfig,
            $errors,
        );

        $metadataFromUploads = [];

        foreach ($this->collectUploadedFiles($request, $key) as $index => $uploadedFile) {
            $metadata = $this->storeUploadedFile(
                $collection,
                $uploadedFile,
                $fieldConfig,
                $key,
                $index,
                $errors,
                $storedPaths,
            );

            if ($metadata !== null) {
                $metadataFromUploads[] = $metadata;
            }
        }

        return [...$metadataFromInput, ...$metadataFromUploads];
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function collectUploadedFiles(Request $request, string $key): array
    {
        $raw = $request->file($key);

        if ($raw instanceof UploadedFile) {
            return [$raw];
        }

        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->flatten(10)
            ->filter(fn (mixed $value): bool => $value instanceof UploadedFile)
            ->values()
            ->all();
    }

    /**
     * @param  array{multiple: bool, nullable: bool, min: ?int, max: ?int, max_size_kb: ?int, allowed_mime_types: array<int, string>}  $fieldConfig
     * @param  array<string, array<int, string>>  $errors
     * @param  array<int, string>  $storedPaths
     * @return array{name: string, path: string, size: int, extension: string, mime: string}|null
     */
    private function storeUploadedFile(
        Collection $collection,
        UploadedFile $uploadedFile,
        array $fieldConfig,
        string $attribute,
        int $index,
        array &$errors,
        array &$storedPaths,
    ): ?array {
        $this->assertUploadedFileConstraints($uploadedFile, $attribute, $index, $fieldConfig, $errors);

        $filename = $this->generateStoredFilename($uploadedFile);
        $disk = $this->resolveDisk();
        $storedPath = $uploadedFile->storeAs(
            $this->collectionUploadDirectory($collection),
            $filename,
            $disk,
        );

        if (! is_string($storedPath) || trim($storedPath) === '') {
            $this->addError($errors, $attribute, 'Failed to store uploaded file.');

            return null;
        }

        $storedPaths[] = $storedPath;

        $extension = strtolower((string) ($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension() ?: pathinfo($filename, PATHINFO_EXTENSION)));
        $mime = (string) ($uploadedFile->getMimeType() ?: $uploadedFile->getClientMimeType() ?: 'application/octet-stream');
        $size = max(0, (int) ($uploadedFile->getSize() ?? 0));

        return [
            'name' => (string) ($uploadedFile->getClientOriginalName() ?: basename($storedPath)),
            'path' => $storedPath,
            'size' => $size,
            'extension' => $extension,
            'mime' => $mime,
        ];
    }

    /**
     * @param  array{multiple: bool, nullable: bool, min: ?int, max: ?int, max_size_kb: ?int, allowed_mime_types: array<int, string>}  $fieldConfig
     * @param  array<string, array<int, string>>  $errors
     */
    private function assertUploadedFileConstraints(UploadedFile $uploadedFile, string $attribute, int $index, array $fieldConfig, array &$errors): void
    {
        $maxSizeKb = $fieldConfig['max_size_kb'];
        if (is_int($maxSizeKb) && $maxSizeKb > 0) {
            $sizeInBytes = max(0, (int) ($uploadedFile->getSize() ?? 0));
            $sizeInKb = (int) ceil($sizeInBytes / 1024);

            if ($sizeInKb > $maxSizeKb) {
                $this->addError(
                    $errors,
                    $attribute,
                    "File at index {$index} exceeds max_size_kb of {$maxSizeKb}."
                );
            }
        }

        $allowedMimeTypes = $fieldConfig['allowed_mime_types'];
        if ($allowedMimeTypes !== []) {
            $mime = (string) ($uploadedFile->getMimeType() ?: $uploadedFile->getClientMimeType() ?: '');
            if ($mime === '' || ! $this->mimeAllowed($mime, $allowedMimeTypes)) {
                $allowed = implode(', ', $allowedMimeTypes);
                $this->addError(
                    $errors,
                    $attribute,
                    "File at index {$index} has MIME type '{$mime}' which is not allowed. Allowed: {$allowed}."
                );
            }
        }
    }

    /**
     * @param  array{multiple: bool, nullable: bool, min: ?int, max: ?int, max_size_kb: ?int, allowed_mime_types: array<int, string>}  $fieldConfig
     * @param  array<string, array<int, string>>  $errors
     * @return array<int, array{name: string, path: string, size: int, extension: string, mime: string}>
     */
    private function normalizeInputMetadata(mixed $raw, string $attribute, array $fieldConfig, array &$errors): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if ($raw instanceof UploadedFile) {
            return [];
        }

        if (is_array($raw)) {
            $flattened = collect($raw)->flatten(10);

            if ($flattened->isNotEmpty() && $flattened->every(fn (mixed $item): bool => $item instanceof UploadedFile)) {
                return [];
            }
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            } else {
                $this->addError($errors, $attribute, 'The field must be a metadata object or an array of metadata objects.');

                return [];
            }
        }

        if (! is_array($raw)) {
            $this->addError($errors, $attribute, 'The field must be a metadata object or an array of metadata objects.');

            return [];
        }

        if (! array_is_list($raw)) {
            $raw = [$raw];
        }

        $normalized = [];

        foreach ($raw as $index => $item) {
            if (! is_array($item)) {
                $this->addError($errors, $attribute, "Metadata item at index {$index} is invalid.");

                continue;
            }

            $metadata = $this->normalizeMetadataObject($item);
            $this->assertMetadataConstraints($metadata, $attribute, $index, $fieldConfig, $errors);

            $normalized[] = $metadata;
        }

        return $normalized;
    }

    /**
     * @param  array{multiple: bool, nullable: bool, min: ?int, max: ?int, max_size_kb: ?int, allowed_mime_types: array<int, string>}  $fieldConfig
     * @param  array<string, array<int, string>>  $errors
     */
    private function assertMetadataConstraints(array $metadata, string $attribute, int $index, array $fieldConfig, array &$errors): void
    {
        $maxSizeKb = $fieldConfig['max_size_kb'];
        if (is_int($maxSizeKb) && $maxSizeKb > 0) {
            $sizeInBytes = max(0, (int) ($metadata['size'] ?? 0));
            $sizeInKb = (int) ceil($sizeInBytes / 1024);

            if ($sizeInKb > $maxSizeKb) {
                $this->addError(
                    $errors,
                    $attribute,
                    "Metadata item at index {$index} exceeds max_size_kb of {$maxSizeKb}."
                );
            }
        }

        $allowedMimeTypes = $fieldConfig['allowed_mime_types'];
        if ($allowedMimeTypes !== []) {
            $mime = (string) ($metadata['mime'] ?? '');
            if ($mime === '' || ! $this->mimeAllowed($mime, $allowedMimeTypes)) {
                $allowed = implode(', ', $allowedMimeTypes);
                $this->addError(
                    $errors,
                    $attribute,
                    "Metadata item at index {$index} has MIME type '{$mime}' which is not allowed. Allowed: {$allowed}."
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{name: string, path: string, size: int, extension: string, mime: string}
     */
    private function normalizeMetadataObject(array $metadata): array
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

    /**
     * @param  array{name: string, path: string, size: int, extension: string, mime: string}  $metadata
     */
    private function metadataMatchesSelector(array $metadata, array $selector): bool
    {
        $selectorPath = trim((string) ($selector['path'] ?? ''));
        if ($selectorPath !== '') {
            return $selectorPath === (string) ($metadata['path'] ?? '');
        }

        $candidateKeys = ['name', 'size', 'extension', 'mime'];
        $hasCandidate = false;

        foreach ($candidateKeys as $key) {
            if (! array_key_exists($key, $selector)) {
                continue;
            }

            $selectorValue = $selector[$key];
            if ($selectorValue === null || $selectorValue === '') {
                continue;
            }

            $hasCandidate = true;

            if ((string) ($metadata[$key] ?? '') !== (string) $selectorValue) {
                return false;
            }
        }

        return $hasCandidate;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<int, array{path?: string, name?: string, size?: int, extension?: string, mime?: string}>
     */
    private function normalizeRemovalSelectors(mixed $raw, string $attribute, array &$errors): array
    {
        if ($raw === null || $raw === '') {
            $this->addError($errors, $attribute, 'Remove operation expects metadata selectors.');

            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            } else {
                $raw = [
                    ['path' => $raw],
                ];
            }
        }

        if (! is_array($raw)) {
            $this->addError($errors, $attribute, 'Remove operation expects an array of metadata selectors.');

            return [];
        }

        if (! array_is_list($raw)) {
            $raw = [$raw];
        }

        $selectors = [];

        foreach ($raw as $index => $item) {
            if (is_string($item) && trim($item) !== '') {
                $selectors[] = ['path' => trim($item)];

                continue;
            }

            if (! is_array($item)) {
                $this->addError($errors, $attribute, "Selector at index {$index} is invalid.");

                continue;
            }

            $selector = [];
            $path = trim((string) ($item['path'] ?? ''));
            if ($path !== '') {
                $selector['path'] = $path;
            }

            foreach (['name', 'size', 'extension', 'mime'] as $key) {
                if (! array_key_exists($key, $item)) {
                    continue;
                }

                $selector[$key] = $item[$key];
            }

            if ($selector === []) {
                $this->addError($errors, $attribute, "Selector at index {$index} must include at least path or identifying metadata.");

                continue;
            }

            $selectors[] = $selector;
        }

        return $selectors;
    }

    /**
     * @param  array<int, array{name: string, path: string, size: int, extension: string, mime: string}>  $metadata
     * @param  array<int, array{path?: string, name?: string, size?: int, extension?: string, mime?: string}>  $selectors
     * @return array{remaining: array<int, array{name: string, path: string, size: int, extension: string, mime: string}>, removed: array<int, array{name: string, path: string, size: int, extension: string, mime: string}>}
     */
    private function splitByRemovalSelectors(array $metadata, array $selectors): array
    {
        if ($selectors === []) {
            return [
                'remaining' => $metadata,
                'removed' => [],
            ];
        }

        $remaining = [];
        $removed = [];

        foreach ($metadata as $item) {
            $matched = false;

            foreach ($selectors as $selector) {
                if ($this->metadataMatchesSelector($item, $selector)) {
                    $matched = true;

                    break;
                }
            }

            if ($matched) {
                $removed[] = $item;

                continue;
            }

            $remaining[] = $item;
        }

        return [
            'remaining' => array_values($remaining),
            'removed' => array_values($removed),
        ];
    }

    /**
     * @param  array<int, array{name: string, path: string, size: int, extension: string, mime: string}>  $metadata
     * @param  array{multiple: bool, nullable: bool, min: ?int, max: ?int, max_size_kb: ?int, allowed_mime_types: array<int, string>}  $fieldConfig
     * @param  array<string, array<int, string>>  $errors
     */
    private function assertCountConstraints(string $attribute, array $metadata, array $fieldConfig, array &$errors): void
    {
        $count = count($metadata);
        $multiple = (bool) ($fieldConfig['multiple'] ?? false);
        $nullable = (bool) ($fieldConfig['nullable'] ?? false);
        $min = $fieldConfig['min'];
        $max = $fieldConfig['max'];

        if (! $multiple && $count > 1) {
            $this->addError($errors, $attribute, 'This field only supports a single file.');
        }

        if (! $nullable && $count === 0) {
            $this->addError($errors, $attribute, 'At least one file is required for this field.');
        }

        if (is_int($min) && $count < $min) {
            $this->addError($errors, $attribute, "At least {$min} file(s) are required.");
        }

        if (is_int($max) && $count > $max) {
            $this->addError($errors, $attribute, "No more than {$max} file(s) are allowed.");
        }
    }

    /**
     * @return array<int, array{name: string, path: string, size: int, extension: string, mime: string}>
     */
    private function normalizeStoredMetadata(mixed $value): array
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
            ->map(fn (array $item): array => $this->normalizeMetadataObject($item))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string, path: string, size: int, extension: string, mime: string}>  $metadata
     * @return array<int, string>
     */
    private function extractPaths(array $metadata): array
    {
        return collect($metadata)
            ->pluck('path')
            ->filter(fn (mixed $path): bool => is_string($path) && trim($path) !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $allowedMimeTypes
     */
    private function mimeAllowed(string $mime, array $allowedMimeTypes): bool
    {
        $mime = strtolower(trim($mime));

        foreach ($allowedMimeTypes as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed === '') {
                continue;
            }

            if (str_ends_with($allowed, '/*')) {
                $prefix = substr($allowed, 0, -1);
                if ($prefix !== '' && str_starts_with($mime, $prefix)) {
                    return true;
                }

                continue;
            }

            if ($mime === $allowed) {
                return true;
            }
        }

        return false;
    }

    private function collectionUploadDirectory(Collection $collection): string
    {
        $safeCollectionName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection->name) ?: 'collection';

        return 'uploads/collections/'.$safeCollectionName;
    }

    private function generateStoredFilename(UploadedFile $uploadedFile): string
    {
        $extension = strtolower((string) ($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension() ?: ''));
        $suffix = $extension !== '' ? '.'.$extension : '';

        return (string) Str::uuid().$suffix;
    }

    private function resolveDisk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<int, mixed>  $allowedMimeTypes
     * @return array<int, string>
     */
    private function normalizeAllowedMimeTypes(array $allowedMimeTypes): array
    {
        return collect($allowedMimeTypes)
            ->filter(fn (mixed $mime): bool => is_string($mime) && trim($mime) !== '')
            ->map(fn (string $mime): string => trim($mime))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function addError(array &$errors, string $attribute, string $message): void
    {
        $errors[$attribute] ??= [];
        $errors[$attribute][] = $message;
    }
}
