<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;
use Veloquent\Core\Domain\SchemaManagement\Models\SchemaJob;

class EndSchemaJob
{
    public function handle(SyncContext $context, Closure $next)
    {
        SchemaJob::where('collection_id', $context->collection->id)->delete();

        return $next($context);
    }
}
