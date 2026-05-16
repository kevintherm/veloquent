<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;
use Closure;

class ComputeSchemaDiff
{
    public function handle(SyncContext $context, Closure $next)
    {
        $context->schemaChange = SchemaChange::diff(
            $context->collection->getOriginal('fields') ?? [],
            $context->newFields
        );

        return $next($context);
    }
}
