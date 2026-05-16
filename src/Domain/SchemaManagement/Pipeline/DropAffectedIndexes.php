<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;
use Veloquent\Core\Domain\SchemaManagement\Services\IndexSyncService;

class DropAffectedIndexes
{
    public function __construct(private readonly IndexSyncService $indexSyncService) {}

    public function handle(SyncContext $context, Closure $next)
    {
        if (!$context->schemaChange) {
            return $next($context);
        }

        $droppedColumns = collect($context->schemaChange->drops)->pluck('name')->all();
        $renamedColumns = collect($context->schemaChange->renames)->pluck(0)->all();

        $this->indexSyncService->dropIndexesReferencingColumns(
            $context->getTableName(),
            [...$droppedColumns, ...$renamedColumns]
        );

        return $next($context);
    }
}
