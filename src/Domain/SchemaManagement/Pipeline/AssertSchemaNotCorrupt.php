<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\SchemaManagement\Services\SchemaCorruptGuard;
use Closure;

class AssertSchemaNotCorrupt
{
    public function __construct(private readonly SchemaCorruptGuard $guard) {}

    public function handle(SyncContext $context, Closure $next)
    {
        if ($context->collection && $context->collection->exists) {
            $this->guard->assertNotCorrupt($context->collection);
        }

        return $next($context);
    }
}
