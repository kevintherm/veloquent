<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SchemaTransferService
{
    /**
     * @var list<string>
     */
    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'schema_jobs',
        'migrations',
    ];

    /**
     * @var list<string>
     */
    private const EXPOSED_SYSTEM_TABLES = [
        'collections',
        'superusers',
        'email_templates',
        'auth_tokens',
        'otp_tokens',
        'oauth_providers',
        'oauth_accounts',
        'realtime_subscriptions',
    ];

    public function __construct(
        private readonly CreateCollectionAction $createCollectionAction,
        private readonly UpdateCollectionAction $updateCollectionAction,
    ) {}

    /**
     * @return array{collections: list<array<string, mixed>>, system_tables: list<string>}
     */
    public function options(): array
    {
        $collections = Collection::query()
            ->orderBy('name')
            ->get(['id', 'name', 'table_name', 'is_system'])
            ->map(fn (Collection $collection): array => [
                'id' => $collection->id,
                'name' => $collection->name,
                'table_name' => $collection->table_name,
                'is_system' => (bool) $collection->is_system,
            ])
            ->values()
            ->all();

        return [
            'collections' => $collections,
            'system_tables' => $this->allowedSystemTables(),
        ];
    }

    /**
     * @param  list<string>  $collectionNames
     * @param  list<string>  $systemTables
     * @return array<string, mixed>
     */
    public function export(array $collectionNames, array $systemTables, bool $includeRecords = true): array
    {
        $this->assertSystemTablesAreAllowed($systemTables);

        $collectionsQuery = Collection::query()
            ->orderBy('name')
            ->whereIn('name', $collectionNames);

        $collections = $collectionsQuery->get();

        $payload = [
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'metadata' => [
                'collections' => $collections->map(
                    fn (Collection $collection): array => $this->exportCollectionMetadata($collection)
                )->values()->all(),
            ],
        ];

        if (! $includeRecords) {
            return $payload;
        }

        $records = [];

        foreach ($collections as $collection) {
            $tableName = $collection->getPhysicalTableName();
            $this->assertTableExists($tableName);
            $records[$tableName] = $this->dumpTableRecords($tableName);
        }

        foreach ($systemTables as $tableName) {
            if (isset($records[$tableName])) {
                continue;
            }

            $this->assertTableExists($tableName);
            $records[$tableName] = $this->dumpTableRecords($tableName);
        }

        $payload['records'] = $records;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function import(array $payload, string $conflict = 'skip'): array
    {
        $conflict = $conflict === 'overwrite' ? 'overwrite' : 'skip';

        $result = [
            'metadata' => [],
            'records' => [],
        ];

        $apiRulesToApply = [];

        $metadataCollections = data_get($payload, 'metadata.collections', []);

        if (is_array($metadataCollections)) {
            foreach ($metadataCollections as $collectionRow) {
                if (! is_array($collectionRow)) {
                    continue;
                }

                $result['metadata'][] = $this->importCollectionMetadataRow($collectionRow, $conflict, $apiRulesToApply);
            }
        }

        $records = data_get($payload, 'records', []);

        if (! is_array($records)) {
            $this->applyDeferredApiRules($apiRulesToApply);

            return $result;
        }

        /** @var SupportCollection<string, Collection> $collectionsByTable */
        $collectionsByTable = Collection::query()->get()->keyBy('table_name');

        $systemTables = $this->systemTablesFromPayload($records, $collectionsByTable);

        foreach ($systemTables as $tableName) {
            $tableRows = $records[$tableName] ?? [];
            $rows = is_array($tableRows) ? $tableRows : [];

            $result['records'][$tableName] = $this->importTableRows(
                $tableName,
                $rows,
                $conflict,
                $collectionsByTable->get($tableName),
                false,
                $apiRulesToApply
            );
        }

        $userDefinedTables = collect(array_keys($records))
            ->filter(fn (mixed $table): bool => is_string($table) && ! in_array($table, $systemTables, true))
            ->values();

        foreach ($userDefinedTables as $tableName) {
            $collection = $collectionsByTable->get($tableName);

            if (! $collection instanceof Collection) {
                throw new RuntimeException("Table '{$tableName}' is not importable.");
            }

            if ($collection->is_system) {
                throw new RuntimeException("System table '{$tableName}' must be imported in the system phase.");
            }

            $tableRows = $records[$tableName] ?? [];
            $rows = is_array($tableRows) ? $tableRows : [];

            $result['records'][$tableName] = DB::transaction(function () use ($tableName, $rows, $conflict, $collection, &$apiRulesToApply): array {
                return $this->importTableRows(
                    $tableName,
                    $rows,
                    $conflict,
                    $collection,
                    true,
                    $apiRulesToApply
                );
            });
        }

        $this->applyDeferredApiRules($apiRulesToApply);

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $apiRulesToApply
     */
    private function applyDeferredApiRules(array $apiRulesToApply): void
    {
        foreach ($apiRulesToApply as $collectionName => $apiRules) {
            $existing = Collection::query()->where('name', $collectionName)->first();
            
            if ($existing instanceof Collection) {
                $this->updateCollectionAction->execute($existing, ['api_rules' => $apiRules]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function allowedSystemTables(): array
    {
        return array_values(array_filter(
            self::EXPOSED_SYSTEM_TABLES,
            fn (string $tableName): bool => ! in_array($tableName, self::EXCLUDED_TABLES, true)
        ));
    }

    /**
     * @param  list<string>  $systemTables
     */
    private function assertSystemTablesAreAllowed(array $systemTables): void
    {
        $allowed = $this->allowedSystemTables();

        foreach ($systemTables as $tableName) {
            if (! in_array($tableName, $allowed, true)) {
                throw new RuntimeException("Table '{$tableName}' is not allowed for export/import.");
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function exportCollectionMetadata(Collection $collection): array
    {
        return [
            'type' => $collection->type->value,
            'is_system' => (bool) $collection->is_system,
            'name' => $collection->name,
            'description' => $collection->description,
            'fields' => $collection->fields,
            'api_rules' => $collection->api_rules,
            'indexes' => $collection->indexes,
            'options' => $collection->options,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dumpTableRecords(string $tableName): array
    {
        return DB::table($tableName)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->values()
            ->all();
    }

    private function importCollectionMetadataRow(array $row, string $conflict, array &$apiRulesToApply): array
    {
        $name = (string) ($row['name'] ?? '');
        $type = (string) ($row['type'] ?? CollectionType::Base->value);

        if ($name === '') {
            throw new RuntimeException('Collection metadata import failed: missing collection name.');
        }

        $isAuthCollection = $type === CollectionType::Auth->value;

        $rawFields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
        $filteredFields = collect($rawFields)
            ->filter(fn (mixed $field): bool => is_array($field))
            ->reject(function (array $field) use ($isAuthCollection): bool {
                $fieldName = $field['name'] ?? null;

                if (! is_string($fieldName)) {
                    return true;
                }

                return in_array($fieldName, SchemaChangePlan::getAllReservedFields($isAuthCollection), true);
            })
            ->values()
            ->all();

        $payload = [
            'type' => $type,
            'is_system' => (bool) ($row['is_system'] ?? false),
            'name' => $name,
            'description' => $row['description'] ?? null,
            'fields' => $filteredFields,
            'indexes' => is_array($row['indexes'] ?? null) ? $row['indexes'] : [],
            'options' => is_array($row['options'] ?? null) ? $row['options'] : [],
        ];

        $existing = Collection::query()->where('name', $name)->first();

        if (! $existing instanceof Collection) {
            $created = $this->createCollectionAction->execute($payload);

            $apiRulesToApply[$created->name] = is_array($row['api_rules'] ?? null) ? $row['api_rules'] : [];

            return [
                'collection' => $created->name,
                'action' => 'created',
            ];
        }

        if ($conflict === 'skip') {
            return [
                'collection' => $existing->name,
                'action' => 'skipped',
            ];
        }

        $updated = $this->updateCollectionAction->execute($existing, Arr::except($payload, ['type', 'is_system']));

        $apiRulesToApply[$updated->name] = is_array($row['api_rules'] ?? null) ? $row['api_rules'] : [];

        return [
            'collection' => $updated->name,
            'action' => 'updated',
        ];
    }

    /**
     * @param  array<string, mixed>  $records
     * @param  SupportCollection<string, Collection>  $collectionsByTable
     * @return list<string>
     */
    private function systemTablesFromPayload(array $records, SupportCollection $collectionsByTable): array
    {
        $tables = [];

        foreach (array_keys($records) as $tableName) {
            if (! is_string($tableName)) {
                continue;
            }

            if ($tableName === 'collections') {
                $tables[] = $tableName;

                continue;
            }

            if (in_array($tableName, $this->allowedSystemTables(), true)) {
                $tables[] = $tableName;

                continue;
            }

            $collection = $collectionsByTable->get($tableName);

            if ($collection instanceof Collection && $collection->is_system) {
                $tables[] = $tableName;
            }
        }

        return array_values(array_unique($tables));
    }

    private function importTableRows(
        string $tableName,
        array $rows,
        string $conflict,
        ?Collection $collection,
        bool $useTransaction,
        array &$apiRulesToApply,
    ): array {
        $this->assertTableExists($tableName);

        $result = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if ($tableName === 'collections') {
                $metadataResult = $this->importCollectionMetadataRow($row, $conflict, $apiRulesToApply);
                $action = $metadataResult['action'] ?? 'skipped';

                if ($action === 'created') {
                    $result['inserted']++;
                } elseif ($action === 'updated') {
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                }

                continue;
            }

            if ($collection instanceof Collection) {
                $rowResult = $this->importCollectionRecord($collection, $row, $conflict);
            } else {
                if (! in_array($tableName, $this->allowedSystemTables(), true)) {
                    throw new RuntimeException("Table '{$tableName}' is not importable.");
                }

                $rowResult = $this->importPlainTableRow($tableName, $row, $conflict);
            }

            $result[$rowResult]++;
        }

        if ($useTransaction) {
            return $result;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'inserted'|'updated'|'skipped'
     */
    private function importCollectionRecord(Collection $collection, array $row, string $conflict): string
    {
        $recordModel = Record::of($collection);

        $recordId = $row['id'] ?? null;

        if (is_string($recordId) && $recordId !== '') {
            $existingRecord = $recordModel->newQuery()->where('id', $recordId)->first();

            if ($existingRecord !== null) {
                if ($conflict === 'skip') {
                    return 'skipped';
                }

                $existingRecord->fill($row);
                $existingRecord->save();

                return 'updated';
            }
        }

        $recordModel->create($row);

        return 'inserted';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'inserted'|'updated'|'skipped'
     */
    private function importPlainTableRow(string $tableName, array $row, string $conflict): string
    {
        $id = $row['id'] ?? null;

        if ($id !== null) {
            $existing = DB::table($tableName)->where('id', $id)->first();

            if ($existing !== null) {
                if ($conflict === 'skip') {
                    return 'skipped';
                }

                DB::table($tableName)->where('id', $id)->update($row);

                return 'updated';
            }
        }

        DB::table($tableName)->insert($row);

        return 'inserted';
    }

    private function assertTableExists(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            throw new RuntimeException("Table '{$tableName}' does not exist.");
        }
    }
}
