<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;
use Veloquent\Core\Domain\SchemaManagement\Services\IndexSyncService;

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
