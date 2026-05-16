<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Support\PivotTableName;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaDDLService;

class SyncPivotTables
{
    public function __construct(private readonly SchemaDDLService $ddlService) {}

    public function handle(SyncContext $context, Closure $next)
    {
        $change = $context->schemaChange;
        if (! $change) {
            return $next($context);
        }

        $sourceName = $context->collection->name;

        // Adds
        foreach ($change->pivotAdds as $field) {
            $targetCollection = Collection::findByIdCached($field['target_collection_id'] ?? '');
            $targetName = $targetCollection?->name ?? 'unknown';

            $this->ddlService->createPivotTable(
                PivotTableName::for($sourceName, $targetName, $field['name']),
                'source_id',
                'target_id',
                $field['pivot_fields'] ?? []
            );
        }

        // Drops
        foreach ($change->pivotDrops as $field) {
            $targetCollection = Collection::findByIdCached($field['target_collection_id'] ?? '');
            $targetName = $targetCollection?->name ?? 'unknown';

            $this->ddlService->deleteTable(
                PivotTableName::for($sourceName, $targetName, $field['name'])
            );
        }

        // Renames
        foreach ($change->pivotRenames as $rename) {
            $targetCollection = Collection::findByIdCached($rename['target'] ?? '');
            $targetName = $targetCollection?->name ?? 'unknown';

            $this->ddlService->renameTable(
                PivotTableName::for($sourceName, $targetName, $rename['from']),
                PivotTableName::for($sourceName, $targetName, $rename['to'])
            );
        }

        // Retargets (Destructive)
        foreach ($change->pivotRetargets as $retarget) {
            // Drop the old pivot table
            $oldTargetCollection = Collection::findByIdCached($retarget['old']['target_collection_id'] ?? '');
            $oldTargetName = $oldTargetCollection?->name ?? 'unknown';
            $oldPivotTable = PivotTableName::for($sourceName, $oldTargetName, $retarget['old']['name']);
            $this->ddlService->deleteTable($oldPivotTable);

            // Create the new pivot table
            $newTargetCollection = Collection::findByIdCached($retarget['new']['target_collection_id'] ?? '');
            $newTargetName = $newTargetCollection?->name ?? 'unknown';
            $newPivotTable = PivotTableName::for($sourceName, $newTargetName, $retarget['new']['name']);

            $this->ddlService->createPivotTable(
                $newPivotTable,
                'source_id',
                'target_id',
                $retarget['new']['pivot_fields'] ?? []
            );
        }

        // Modifications (Extra columns)
        foreach ($change->pivotModifies as $mod) {
            $field = $mod['new'];
            $columnChanges = $mod['changes'] ?? null;

            if (! $columnChanges) {
                continue;
            }

            $targetCollection = Collection::findByIdCached($field['target_collection_id'] ?? '');
            $targetName = $targetCollection?->name ?? 'unknown';

            $this->ddlService->applyChange(
                PivotTableName::for($sourceName, $targetName, $field['name']),
                $columnChanges
            );
        }

        return $next($context);
    }
}
