<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\SchemaManagement\Models\SchemaJob;
use Closure;

class StartSchemaJob
{
    public function handle(SyncContext $context, Closure $next)
    {
        SchemaJob::create([
            'collection_id' => $context->collection->id,
            'operation' => $context->operation,
            'table_name' => $context->getTableName(),
            'started_at' => now(),
        ]);

        return $next($context);
    }
}
