<?php

namespace Veloquent\Core\Domain\Records\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Veloquent\Core\Support\Database\SchemaCache;

class PivotSyncService
{
    /**
     * Performs a full-state synchronization of a pivot table.
     * 
     * This method ensures the pivot table exactly matches the provided $entries.
     * It works by removing all existing associations for the $sourceId and re-inserting the new ones.
     */
    public function sync(
        string $pivotTable,
        string $sourceIdColumn,
        string $targetIdColumn,
        string $sourceId,
        array $entries
    ): void {
        if (! SchemaCache::hasTable($pivotTable)) {
            return;
        }

        // Clear existing associations to ensure an idempotent state
        $this->detachAll($pivotTable, $sourceIdColumn, $sourceId);

        if (empty($entries)) {
            return;
        }

        $rowsToInsert = $this->buildInsertRows($sourceIdColumn, $targetIdColumn, $sourceId, $entries);

        if (! empty($rowsToInsert)) {
            DB::table($pivotTable)->insert($rowsToInsert);
        }
    }

    /**
     * Removes all pivot table records associated with a specific source ID.
     */
    public function detachAll(string $pivotTable, string $sourceIdColumn, string $sourceId): void
    {
        if (! SchemaCache::hasTable($pivotTable)) {
            return;
        }

        DB::table($pivotTable)->where($sourceIdColumn, $sourceId)->delete();
    }

    /**
     * Builds the final array of rows to be inserted into the pivot table.
     */
    private function buildInsertRows(string $sourceIdCol, string $targetIdCol, string $sourceId, array $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $targetId = is_array($entry) ? ($entry['id'] ?? null) : $entry;

            if (! is_string($targetId) || $targetId === '') {
                continue;
            }

            // Start with the base relationship data
            $row = [
                'id' => (string) Str::ulid(),
                $sourceIdCol => $sourceId,
                $targetIdCol => $targetId,
                'created_at' => now(),
            ];

            // If additional pivot column data was provided, merge it in
            if (is_array($entry)) {
                $pivotData = $entry;
                unset($pivotData['id']);
                
                $row = array_merge($row, $pivotData);
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
