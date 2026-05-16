<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\SchemaManagement\Services\IndexSyncService;
use Closure;

class SyncIndexes
{
    public function __construct(private readonly IndexSyncService $indexSyncService) {}

    public function handle(SyncContext $context, Closure $next)
    {
        $this->indexSyncService->sync(
            $context->getTableName(),
            $context->newIndexes
        );

        return $next($context);
    }
}
