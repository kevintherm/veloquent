<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Index;
use App\Domain\SchemaManagement\Enums\SchemaOperation;
use App\Domain\SchemaManagement\Models\SchemaJob;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SchemaRecoveryService
{
    public function __construct(
        private readonly SchemaDDLService $ddlService,
        private readonly IndexSyncService $indexSyncService,
    ) {
    }

    /**
     * Recover a collection from a corrupt schema state.
     */
    public function recover(Collection $collection): void
    {
        $job = SchemaJob::where('collection_id', $collection->id)->first();

        if (!$job) {
            return;
        }

        match ($job->operation) {
            SchemaOperation::Create => $this->recoverFromFailedCreate($collection, $job),
            SchemaOperation::Update => $this->recoverFromFailedUpdate($collection, $job),
            SchemaOperation::Drop => $this->recoverFromFailedDrop($collection, $job),
            default => throw new RuntimeException("Unsupported recovery operation: {$job->operation->value}"),
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

        $fieldsForDDL = SchemaChangePlan::stripForDDL($collection->fields ?? []);
        $this->ddlService->createTable($tableName, $fieldsForDDL);

        $desiredIndexes = $this->extractIndexes($collection->indexes ?? []);
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

    /**
     * @return array<int, Index>
     */
    private function extractIndexes(mixed $indexes): array
    {
        if (is_array($indexes)) {
            return collect($indexes)
                ->map(fn(mixed $index) => Index::fromArray((array) $index))
                ->values()
                ->all();
        }

        return [];
    }
}
