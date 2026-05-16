<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaDDLService;

class ApplyDDLChanges
{
    public function __construct(private readonly SchemaDDLService $ddlService) {}

    public function handle(SyncContext $context, Closure $next)
    {
        if ($context->schemaChange) {
            $this->ddlService->applyChange(
                $context->getTableName(),
                $context->schemaChange
            );
        }

        return $next($context);
    }
}
