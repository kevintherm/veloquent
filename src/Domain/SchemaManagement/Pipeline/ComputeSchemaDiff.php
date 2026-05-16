<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;

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
