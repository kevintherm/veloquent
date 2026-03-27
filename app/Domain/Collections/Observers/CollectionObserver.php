<?php

namespace App\Domain\Collections\Observers;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Index;
use App\Domain\Records\Services\RelationIntegrityService;
use App\Domain\SchemaManagement\Enums\SchemaOperation;
use App\Domain\SchemaManagement\Models\SchemaJob;
use App\Domain\SchemaManagement\Services\IndexSyncService;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use App\Domain\SchemaManagement\Services\SchemaCorruptGuard;
use App\Domain\SchemaManagement\Services\SchemaDDLService;
use App\Infrastructure\Exceptions\InvalidArgumentException;
use Exception;
use Illuminate\Support\Facades\DB;

readonly class CollectionObserver
{
    public function __construct(
        private SchemaDDLService $ddlService,
        private IndexSyncService $indexSyncService,
        private RelationIntegrityService $relationIntegrityService,
        private SchemaCorruptGuard $corruptGuard,
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function creating(Collection $collection): void
    {
        $this->corruptGuard->assertNotCorrupt($collection);

        $this->validateApiRules($collection);
        $this->ensureTableNameIsSet($collection);

        $this->startJob($collection, SchemaOperation::Create);

        try {
            $this->createSchema($collection);
        } catch (\Throwable $e) {
            $this->handleJobFailure($collection, $e);
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
        $this->corruptGuard->assertNotCorrupt($collection);

        $this->validateApiRules($collection);
        $this->ensureTypeIsNotChanged($collection);

        $this->startJob($collection, SchemaOperation::Update);

        try {
            $this->updateSchema($collection);
        } catch (\Throwable $e) {
            $this->handleJobFailure($collection, $e);
        }
    }

    public function updated(Collection $collection): void
    {
        $this->endJob($collection);
    }

    public function deleting(Collection $collection): void
    {
        $this->relationIntegrityService->assertCollectionCanBeDeleted($collection);

        $this->startJob($collection, SchemaOperation::Drop);

        try {
            $this->ddlService->deleteTable($collection->getPhysicalTableName());
        } catch (\Throwable $e) {
            $this->handleJobFailure($collection, $e);
        }
    }

    public function deleted(Collection $collection): void
    {
        $this->endJob($collection);
    }

    private function ensureTableNameIsSet(Collection $collection): void
    {
        if (empty($collection->table_name)) {
            $collection->table_name = SchemaChangePlan::generateTableName($collection->name, $collection->is_system ?? false);
        }
    }

    private function ensureTypeIsNotChanged(Collection $collection): void
    {
        if ($collection->isDirty('type')) {
            throw new InvalidArgumentException('Collection type cannot be changed');
        }
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

    /**
     * Handle job failure by ending the job and re-throwing the exception.
     * Specifically for MySql driver, it would not rollback the DDL statement,
     * inconsistent metadata state would need a manual cleanup.
     *
     * @return never
     */
    private function handleJobFailure(Collection $collection, \Throwable $e): void
    {
        if (! $this->isMySQL()) {
            $this->endJob($collection);
        }

        throw $e;
    }

    private function createSchema(Collection $collection): void
    {
        $tableName = $collection->getPhysicalTableName();

        $fieldsForDDL = SchemaChangePlan::stripForDDL($collection->fields ?? []);
        $this->ddlService->createTable($tableName, $fieldsForDDL);

        $desiredIndexes = $this->extractIndexes($collection->indexes ?? []);
        $effectiveFields = $this->syncFieldUniqueFlags(
            $this->extractFields($collection->fields ?? []),
            $desiredIndexes
        );
        $collection->fields = $effectiveFields;

        $protectedIndexNames = $this->protectedUniqueIndexNames($tableName, $effectiveFields, $desiredIndexes);
        $this->indexSyncService->sync($tableName, $desiredIndexes, $protectedIndexNames);

        $collection->schema_updated_at = now();
    }

    private function updateSchema(Collection $collection): void
    {
        $fieldsWereDirty = $collection->isDirty('fields');
        $indexesWereDirty = $collection->isDirty('indexes');

        if (! $fieldsWereDirty && ! $indexesWereDirty) {
            $collection->schema_updated_at = now();

            return;
        }

        $tableName = $collection->getPhysicalTableName();
        $isAuthCollection = $collection->type === CollectionType::Auth;

        $originalFields = $this->extractFields($collection->getOriginal('fields'));
        $newFields = $this->extractFields($collection->fields ?? []);
        $desiredIndexes = $this->extractIndexes($collection->indexes ?? []);

        if ($fieldsWereDirty) {
            $affectedColumns = $this->fieldNamesAffectedByUpdate($originalFields, $newFields);
            $this->indexSyncService->dropIndexesReferencingColumns($tableName, $affectedColumns);

            $desiredIndexes = $this->updateIndexesForFieldChanges($originalFields, $newFields, $desiredIndexes);
            $collection->indexes = array_map(
                fn (Index $index): array => $index->toArray(),
                $desiredIndexes
            );

            $this->ddlService->updateTable(
                $tableName,
                SchemaChangePlan::stripForDDL($originalFields),
                SchemaChangePlan::stripForDDL($newFields),
                $isAuthCollection
            );
        }

        $effectiveFields = $fieldsWereDirty ? $newFields : $this->extractFields($collection->fields ?? []);

        $effectiveFields = $this->syncFieldUniqueFlags(
            $effectiveFields,
            $desiredIndexes
        );
        $collection->fields = $effectiveFields;

        $protectedIndexNames = $this->protectedUniqueIndexNames($tableName, $effectiveFields, $desiredIndexes);

        $this->indexSyncService->sync(
            $tableName,
            $desiredIndexes,
            $protectedIndexNames
        );

        $collection->schema_updated_at = now();
    }

    private function validateApiRules(Collection $collection): void
    {
        $defaults = [
            'list' => null,
            'view' => null,
            'create' => null,
            'update' => null,
            'delete' => null,
            'manage' => null,
        ];

        $validKeys = ['list', 'view', 'create', 'update', 'delete', 'manage'];

        $collection->api_rules = array_merge($defaults, $collection->api_rules ?? []);
        $invalidKeys = array_diff(array_keys($collection->api_rules ?? []), $validKeys);

        if (! empty($invalidKeys)) {
            throw new InvalidArgumentException('Invalid api rules keys: '.implode(', ', $invalidKeys));
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
            ->unique(fn (Index $index): string => $index->identityKey())
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
    private function syncFieldUniqueFlags(array $fields, array $indexes): array
    {
        $reservedNames = SchemaChangePlan::getAllReservedFields(false);

        $uniqueFieldNames = collect($indexes)
            ->filter(fn (Index $index): bool => $index->type === 'unique' && count($index->columns) === 1)
            ->map(fn (Index $index): string => (string) $index->columns[0])
            ->values()
            ->all();

        return collect($fields)
            ->map(function (array $field) use ($reservedNames, $uniqueFieldNames): array {
                $fieldName = $field['name'] ?? null;

                if ($fieldName === 'id') {
                    $field['unique'] = true;

                    return $field;
                }

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
