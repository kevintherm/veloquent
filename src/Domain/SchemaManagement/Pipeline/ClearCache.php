<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;

class ClearCache
{
    public function handle(SyncContext $context, Closure $next)
    {
        $context->collection->clearCache();

        return $next($context);
    }
}
