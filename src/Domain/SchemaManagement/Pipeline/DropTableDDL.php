<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\SchemaManagement\Services\SchemaDDLService;
use Closure;

class DropTableDDL
{
    public function __construct(private readonly SchemaDDLService $ddlService) {}

    public function handle(SyncContext $context, Closure $next)
    {
        $this->ddlService->deleteTable($context->getTableName());

        return $next($context);
    }
}
