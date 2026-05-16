<?php

namespace Veloquent\Core\Domain\SchemaManagement\Services;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\ValueObjects\Index;
use Veloquent\Core\Domain\SchemaManagement\Enums\SchemaOperation;
use Veloquent\Core\Domain\SchemaManagement\Models\SchemaJob;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SchemaRecoveryService
{
    public function __construct(
        private readonly SchemaDDLService $ddlService,
        private readonly IndexSyncService $indexSyncService,
    ) {}

    /**
     * Recover a collection from a corrupt schema state.
     */
    public function recover(Collection $collection): void
    {
        $job = SchemaJob::where('collection_id', $collection->id)->first();

        if (! $job) {
            return;
        }

        match ($job->operation) {
            SchemaOperation::Create => $this->recoverFromFailedCreate($collection, $job),
            SchemaOperation::Update => $this->recoverFromFailedUpdate($collection, $job),
            SchemaOperation::Drop => $this->recoverFromFailedDrop($collection, $job),
            default => throw new InvalidArgumentException("Unsupported recovery operation: {$job->operation->value}"),
        };
    }

    private function recoverFromFailedCreate(Collection $collection, SchemaJob $job): void
    {
        // Safe regardless of how far DDL got
        $this->ddlService->deleteTable($job->table_name);

        $job->delete();

        // If the collection was partially created (e.g. record in DB but table failed and transaction didn't roll back)
        if ($collection->exists) {
            $collection->delete();
        }
    }

    private function recoverFromFailedUpdate(Collection $collection, SchemaJob $job): void
    {
        $tableName = $collection->getPhysicalTableName();

        $this->ddlService->deleteTable($tableName);

        $fieldsForDDL = SchemaChange::stripForDDL($collection->fields ?? []);
        $this->ddlService->createTable($tableName, $fieldsForDDL);

        $desiredIndexes = Index::collection($collection->indexes ?? []);
        $this->indexSyncService->sync($tableName, $desiredIndexes, []);

        $job->delete();
    }

    private function recoverFromFailedDrop(Collection $collection, SchemaJob $job): void
    {
        $this->ddlService->deleteTable($job->table_name);
        $job->delete();

        if ($collection->exists) {
            $collection->delete();
        }
    }
}
