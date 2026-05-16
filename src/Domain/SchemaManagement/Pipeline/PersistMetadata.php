<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;

class PersistMetadata
{
    public function handle(SyncContext $context, Closure $next)
    {
        $context->collection->update([
            'fields' => $context->newFields,
            'indexes' => $context->newIndexes,
            'options' => $context->rawData['options'] ?? $context->collection->options ?? [],
            'api_rules' => $context->rawData['api_rules'] ?? $context->collection->api_rules ?? [],
            'schema_updated_at' => now(),
        ]);

        $context->collection->refresh();

        return $next($context);
    }
}
