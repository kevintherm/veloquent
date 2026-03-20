<?php

namespace App\Domain\Collections\Observers;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Index;
use App\Domain\SchemaManagement\Enums\SchemaOperation;
use App\Domain\SchemaManagement\Models\SchemaJob;
use App\Domain\SchemaManagement\Services\IndexSyncService;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use App\Domain\SchemaManagement\Services\SchemaDDLService;
use App\Infrastructure\Exceptions\InvalidArgumentException;
use Illuminate\Support\Facades\DB;

readonly class CollectionObserver
{
    public function __construct(
        private SchemaDDLService $ddlService,
        private IndexSyncService $indexSyncService,
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function creating(Collection $collection): void
    {
        $this->validateApiRules($collection);

        if (empty($collection->table_name)) {
            $collection->table_name = SchemaChangePlan::generateTableName($collection->name, $collection->is_system ?? false);
        }

        $this->startJob($collection, SchemaOperation::Create);

        $tableName = $collection->getPhysicalTableName();
        $isAuthCollection = $collection->type === CollectionType::Auth;

        try {
            $fieldsForDDL = SchemaChangePlan::stripForDDL($collection->fields ?? []);
            $this->ddlService->createTable($tableName, $fieldsForDDL);

            $desiredIndexes = $this->extractIndexes($collection->indexes ?? []);
            $effectiveFields = $this->syncFieldUniqueFlags(
                $this->extractFields($collection->fields ?? []),
                $desiredIndexes,
                $isAuthCollection,
            );
            $collection->fields = $effectiveFields;

            $protectedIndexNames = $this->protectedUniqueIndexNames($tableName, $effectiveFields, $desiredIndexes);
            $this->indexSyncService->sync($tableName, $desiredIndexes, $protectedIndexNames);

            $collection->schema_updated_at = now();
        } catch (\Throwable $e) {
            if (! $this->isMySQL()) {
                SchemaJob::where('table_name', $tableName)->delete();
            }

            throw $e;
        }
    }

    public function created(Collection $collection): void
    {
        $this->endJob($collection);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function updating(Collection $collection): void
    {
        $this->validateApiRules($collection);

        if ($collection->isDirty('type')) {
            throw new InvalidArgumentException('Collection type cannot be changed');
        }

        $this->startJob($collection, SchemaOperation::Update);

        $fieldsWereDirty = $collection->isDirty('fields');
        $indexesWereDirty = $collection->isDirty('indexes');
        $nameWasDirty = $collection->isDirty('name');
        $isAuthCollection = $collection->type === CollectionType::Auth;

        $tableName = $collection->getPhysicalTableName();
        $desiredIndexes = $this->extractIndexes($collection->indexes ?? []);
        $existingIndexesOnTable = $this->extractIndexes($collection->getOriginal('indexes'));

        $originalFields = $this->extractFields($collection->getOriginal('fields'));
        $newFields = $this->extractFields($collection->fields ?? []);

        try {
            if ($nameWasDirty) {
                $oldTableName = Collection::formatTableName($collection->getOriginal('name'), $collection->is_system);
                $this->ddlService->renameTable($oldTableName, $collection->getPhysicalTableName());

                $protectedOldNames = $this->protectedUniqueIndexNames($oldTableName, $originalFields, $existingIndexesOnTable);
                $this->indexSyncService->dropManagedIndexesForPreviousTableName(
                    $tableName,
                    $oldTableName,
                    $protectedOldNames
                );

                $collection->schema_updated_at = now();
            }

            if ($fieldsWereDirty) {
                $preDropIndexNames = $this->indexNamesToDropBeforeFieldChanges(
                    $tableName,
                    $existingIndexesOnTable,
                    $originalFields,
                    $newFields
                );

                $this->indexSyncService->dropIndexesByNames($tableName, $preDropIndexNames);

                $desiredIndexes = $this->updateIndexesForFieldChanges($originalFields, $newFields, $desiredIndexes);
                $collection->indexes = array_map(
                    fn (Index $index): array => $index->toArray(),
                    $desiredIndexes
                );

                $originalFieldsForDDL = SchemaChangePlan::stripForDDL($originalFields);
                $newFieldsForDDL = SchemaChangePlan::stripForDDL($newFields);

                $this->ddlService->updateTable(
                    $tableName,
                    $originalFieldsForDDL,
                    $newFieldsForDDL,
                    $isAuthCollection
                );

                $collection->schema_updated_at = now();
            }

            if ($indexesWereDirty || $fieldsWereDirty || $nameWasDirty) {
                $effectiveFields = $fieldsWereDirty
                    ? $newFields
                    : $this->extractFields($collection->fields ?? []);

                $effectiveFields = $this->syncFieldUniqueFlags(
                    $effectiveFields,
                    $desiredIndexes,
                    $isAuthCollection,
                );
                $collection->fields = $effectiveFields;

                $protectedIndexNames = $this->protectedUniqueIndexNames($tableName, $effectiveFields, $desiredIndexes);

                $this->indexSyncService->sync(
                    $tableName,
                    $desiredIndexes,
                    $protectedIndexNames
                );
            }

            $collection->schema_updated_at = now();
        } catch (\Throwable $e) {
            if (! $this->isMySQL()) {
                SchemaJob::where('table_name', $tableName)->delete();
            }

            throw $e;
        }
    }

    public function updated(Collection $collection): void
    {
        $this->endJob($collection);
    }

    public function deleting(Collection $collection): void
    {
        $this->startJob($collection, SchemaOperation::Drop);

        $this->ddlService->deleteTable($collection->getPhysicalTableName());
    }

    public function deleted(Collection $collection): void
    {
        $this->endJob($collection);
    }

    private function startJob(Collection $collection, SchemaOperation $op): void
    {
        SchemaJob::create([
            'collection_id' => $collection->id,
            'table_name' => $collection->getPhysicalTableName(),
            'operation' => $op,
            'started_at' => now(),
        ]);
    }

    private function endJob(Collection $collection): void
    {
        SchemaJob::where('table_name', $collection->getPhysicalTableName())->delete();
    }

    private function validateApiRules(Collection $collection): void
    {
        $collection->api_rules = array_merge([
            'list' => null,
            'view' => null,
            'create' => null,
            'update' => null,
            'delete' => null,
        ], $collection->api_rules ?? []);

        $validKeys = ['list', 'view', 'create', 'update', 'delete'];
        $invalidKeys = array_diff(array_keys($collection->api_rules ?? []), $validKeys);

        if (! empty($invalidKeys)) {
            throw new \InvalidArgumentException('Invalid api rules keys: '.implode(', ', $invalidKeys));
        }
    }

    private function isMySQL(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    private function extractFields(mixed $fields): array
    {
        if (is_array($fields)) {
            return collect($fields)
                ->map(function (mixed $field): array {
                    if (is_array($field)) {
                        return $field;
                    }

                    if (is_object($field) && method_exists($field, 'toArray')) {
                        return $field->toArray();
                    }

                    return (array) $field;
                })
                ->values()
                ->all();
        }

        if (is_string($fields) && $fields !== '') {
            $decoded = json_decode($fields, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @return array<int, Index>
     */
    private function extractIndexes(mixed $indexes): array
    {
        if (is_array($indexes)) {
            return collect($indexes)
                ->map(function (mixed $index): Index {
                    if ($index instanceof Index) {
                        return $index;
                    }

                    return Index::fromArray((array) $index);
                })
                ->values()
                ->all();
        }

        if (is_string($indexes) && $indexes !== '') {
            $decoded = json_decode($indexes, true);

            if (! is_array($decoded)) {
                return [];
            }

            return collect($decoded)
                ->map(fn (mixed $index): Index => Index::fromArray((array) $index))
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $beforeFields
     * @param  array<int, array<string, mixed>>  $afterFields
     * @param  array<int, Index>  $indexes
     * @return array<int, Index>
     */
    private function updateIndexesForFieldChanges(array $beforeFields, array $afterFields, array $indexes): array
    {
        $beforeById = collect($beforeFields)
            ->filter(fn (array $field): bool => isset($field['id']))
            ->keyBy('id');

        $afterById = collect($afterFields)
            ->filter(fn (array $field): bool => isset($field['id']))
            ->keyBy('id');

        $renames = [];

        foreach ($beforeById as $id => $beforeField) {
            if (! $afterById->has($id)) {
                continue;
            }

            $afterField = $afterById->get($id);

            if (($beforeField['name'] ?? null) !== ($afterField['name'] ?? null)) {
                $renames[(string) $beforeField['name']] = (string) $afterField['name'];
            }
        }

        $droppedNames = $beforeById
            ->reject(fn (array $field, string|int $id): bool => $afterById->has($id))
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();

        $transformed = collect($indexes)
            ->map(function (Index $index) use ($renames): Index {
                foreach ($renames as $from => $to) {
                    $index = $index->withRenamedColumn($from, $to);
                }

                return $index;
            })
            ->reject(function (Index $index) use ($droppedNames): bool {
                foreach ($droppedNames as $droppedName) {
                    if ($index->referencesColumn($droppedName)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(function (Index $index): Index {
                $columns = array_values(array_unique($index->columns));

                return new Index(columns: $columns, type: $index->type);
            })
            ->reject(fn (Index $index): bool => $index->columns === [])
            ->unique(fn (Index $index): string => implode('|', [...$index->columns, $index->type]))
            ->values()
            ->all();

        return $transformed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, Index>  $indexes
     * @return array<int, string>
     */
    private function protectedUniqueIndexNames(string $table, array $fields, array $indexes): array
    {
        $indexManagedUniqueFieldNames = collect($indexes)
            ->filter(fn (Index $index): bool => $index->type === 'unique' && count($index->columns) === 1)
            ->map(fn (Index $index): string => (string) $index->columns[0])
            ->values()
            ->all();

        return collect($fields)
            ->filter(fn (array $field): bool => ($field['unique'] ?? false) === true)
            ->pluck('name')
            ->reject(fn (mixed $name): bool => is_string($name) && in_array($name, $indexManagedUniqueFieldNames, true))
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '' && $name !== 'id')
            ->map(fn (string $name): string => Index::generateIndexName($table, [$name], 'unique'))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, Index>  $indexes
     * @return array<int, array<string, mixed>>
     */
    private function syncFieldUniqueFlags(array $fields, array $indexes, bool $isAuthCollection): array
    {
        $reservedNames = SchemaChangePlan::getAllReservedFields($isAuthCollection);

        $uniqueFieldNames = collect($indexes)
            ->filter(fn (Index $index): bool => $index->type === 'unique' && count($index->columns) === 1)
            ->map(fn (Index $index): string => (string) $index->columns[0])
            ->values()
            ->all();

        return collect($fields)
            ->map(function (array $field) use ($reservedNames, $uniqueFieldNames): array {
                $fieldName = $field['name'] ?? null;

                if (! is_string($fieldName) || in_array($fieldName, $reservedNames, true)) {
                    return $field;
                }

                $field['unique'] = in_array($fieldName, $uniqueFieldNames, true);

                return $field;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, Index>  $existingIndexes
     * @param  array<int, array<string, mixed>>  $beforeFields
     * @param  array<int, array<string, mixed>>  $afterFields
     * @return array<int, string>
     */
    private function indexNamesToDropBeforeFieldChanges(string $table, array $existingIndexes, array $beforeFields, array $afterFields): array
    {
        $affectedOldColumns = $this->fieldNamesAffectedByUpdate($beforeFields, $afterFields);

        if ($affectedOldColumns === []) {
            return [];
        }

        return collect($existingIndexes)
            ->filter(function (Index $index) use ($affectedOldColumns): bool {
                foreach ($affectedOldColumns as $column) {
                    if ($index->referencesColumn($column)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn (Index $index): string => $index->generateName($table))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $beforeFields
     * @param  array<int, array<string, mixed>>  $afterFields
     * @return array<int, string>
     */
    private function fieldNamesAffectedByUpdate(array $beforeFields, array $afterFields): array
    {
        $beforeById = collect($beforeFields)
            ->filter(fn (array $field): bool => isset($field['id']))
            ->keyBy('id');

        $afterById = collect($afterFields)
            ->filter(fn (array $field): bool => isset($field['id']))
            ->keyBy('id');

        $renamedFrom = [];

        foreach ($beforeById as $id => $beforeField) {
            if (! $afterById->has($id)) {
                continue;
            }

            $afterField = $afterById->get($id);

            if (($beforeField['name'] ?? null) !== ($afterField['name'] ?? null)) {
                $renamedFrom[] = (string) $beforeField['name'];
            }
        }

        $dropped = $beforeById
            ->reject(fn (array $field, string|int $id): bool => $afterById->has($id))
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();

        return array_values(array_unique([...$renamedFrom, ...$dropped]));
    }
}
