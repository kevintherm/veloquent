<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaDDLService;
use Closure;

class CreateTableDDL
{
    public function __construct(private readonly SchemaDDLService $ddlService) {}

    public function handle(SyncContext $context, Closure $next)
    {
        if (! $context->isCreate()) {
            return $next($context);
        }

        $fieldsForDdl = SchemaChange::stripForDDL($context->newFields);
        
        $this->ddlService->createTable(
            $context->getTableName(),
            $fieldsForDdl
        );

        return $next($context);
    }
}
