<?php

namespace Veloquent\Core\Domain\SchemaManagement\Services;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\ClearCache;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\SyncContext;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\SyncIndexes;
use Veloquent\Core\Domain\SchemaManagement\Enums\SchemaOperation;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\DropTableDDL;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\EndSchemaJob;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\CreateTableDDL;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\StartSchemaJob;
use Veloquent\Core\Domain\Records\Services\RelationIntegrityService;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\ApplyDDLChanges;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\DropPivotTables;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\PersistMetadata;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\SyncPivotTables;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\ComputeSchemaDiff;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\CreatePivotTables;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\SyncIndexMetadata;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\DropAffectedIndexes;
use Veloquent\Core\Domain\SchemaManagement\Contracts\CollectionSyncService;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\AssertSchemaNotCorrupt;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\CreateCollectionRecord;
use Veloquent\Core\Domain\SchemaManagement\Pipeline\SyncFieldUniqueMetadata;

class DefaultCollectionSyncService implements CollectionSyncService
{
    private static bool $syncing = false;

    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly RelationIntegrityService $relationIntegrityService
    ) {}

    public static function isSyncing(): bool
    {
        return self::$syncing;
    }

    private function runSyncing(callable $callback)
    {
        self::$syncing = true;
        try {
            return $callback();
        } finally {
            self::$syncing = false;
        }
    }

    public function create(array $data): Collection
    {
        if (empty($data['id'])) {
            $data['id'] = (string) \Illuminate\Support\Str::ulid();
        }

        if (isset($data['fields'])) {
            $data['fields'] = $this->resolveSelfReferentialTargetCollections(
                $data['fields'],
                $data['id'],
                $data['type'] ?? null
            );
        }

        return $this->runSyncing(fn () => DB::transaction(function () use ($data) {
            $context = new SyncContext(
                operation: SchemaOperation::Create,
                newFields: $data['fields'] ?? [],
                newIndexes: $data['indexes'] ?? [],
                rawData: $data
            );

            $this->pipeline
                ->send($context)
                ->through([
                    SyncFieldUniqueMetadata::class,
                    CreateCollectionRecord::class,
                    StartSchemaJob::class,
                    CreateTableDDL::class,
                    CreatePivotTables::class,
                    SyncIndexes::class,
                    PersistMetadata::class,
                    EndSchemaJob::class,
                ])
                ->thenReturn();

            return $context->collection;
        }));
    }

    public function update(Collection $collection, array $data): Collection
    {
        if (isset($data['fields'])) {
            $data['fields'] = $this->resolveSelfReferentialTargetCollections(
                $data['fields'],
                $collection->id,
                $collection->type
            );
        }

        return $this->runSyncing(fn () => DB::transaction(function () use ($collection, $data) {
            $context = new SyncContext(
                operation: SchemaOperation::Update,
                collection: $collection,
                newFields: $data['fields'] ?? $collection->fields ?? [],
                newIndexes: $data['indexes'] ?? $collection->indexes ?? [],
                rawData: $data
            );

            $this->pipeline
                ->send($context)
                ->through([
                    AssertSchemaNotCorrupt::class,
                    StartSchemaJob::class,
                    ComputeSchemaDiff::class,
                    SyncIndexMetadata::class,
                    SyncFieldUniqueMetadata::class,
                    DropAffectedIndexes::class,
                    ApplyDDLChanges::class,
                    SyncPivotTables::class,
                    SyncIndexes::class,
                    PersistMetadata::class,
                    EndSchemaJob::class,
                    ClearCache::class,
                ])
                ->thenReturn();

            return $context->collection;
        }));
    }

    public function delete(Collection $collection): void
    {
        $this->relationIntegrityService->assertCollectionCanBeDeleted($collection);

        $this->runSyncing(fn () => DB::transaction(function () use ($collection) {
            $this->drop($collection);

            $collection->delete();
        }));
    }

    public function drop(Collection $collection): void
    {
        $this->runSyncing(fn () => DB::transaction(function () use ($collection) {
            $context = new SyncContext(
                operation: SchemaOperation::Drop,
                collection: $collection
            );

            $this->pipeline
                ->send($context)
                ->through([
                    AssertSchemaNotCorrupt::class,
                    StartSchemaJob::class,
                    DropPivotTables::class,
                    DropTableDDL::class,
                    EndSchemaJob::class,
                    ClearCache::class,
                ])
                ->thenReturn();
        }));
    }

    public function sync(Collection $collection, SchemaOperation $operation = SchemaOperation::Update): void
    {
        $this->runSyncing(fn () => DB::transaction(function () use ($collection, $operation) {
            $context = new SyncContext(
                operation: $operation,
                collection: $collection,
                newFields: $collection->fields ?? [],
                newIndexes: $collection->indexes ?? [],
                rawData: $collection->toArray()
            );

            $pipes = $operation === SchemaOperation::Create
                ? [
                    StartSchemaJob::class,
                    SyncFieldUniqueMetadata::class,
                    CreateTableDDL::class,
                    CreatePivotTables::class,
                    SyncIndexes::class,
                    PersistMetadata::class,
                    EndSchemaJob::class,
                ]
                : [
                    AssertSchemaNotCorrupt::class,
                    StartSchemaJob::class,
                    ComputeSchemaDiff::class,
                    SyncIndexMetadata::class,
                    SyncFieldUniqueMetadata::class,
                    DropAffectedIndexes::class,
                    ApplyDDLChanges::class,
                    SyncPivotTables::class,
                    SyncIndexes::class,
                    PersistMetadata::class,
                    PersistMetadata::class,
                    EndSchemaJob::class,
                    ClearCache::class,
                ];

            $this->pipeline
                ->send($context)
                ->through($pipes)
                ->thenReturn();
        }));
    }

    private function resolveSelfReferentialTargetCollections(array $fields, string $collectionId, mixed $type): array
    {
        if (CollectionType::parse($type) !== CollectionType::Agents) {
            return $fields;
        }

        foreach ($fields as &$field) {
            if (($field['type'] ?? '') === CollectionFieldType::RelationMany->value
                && ($field['target_collection_id'] ?? '') === '@self') {
                $field['target_collection_id'] = $collectionId;
            }
        }

        return $fields;
    }
}
