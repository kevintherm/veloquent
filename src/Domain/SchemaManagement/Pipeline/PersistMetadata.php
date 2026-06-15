<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;

class PersistMetadata
{
    public function handle(SyncContext $context, Closure $next)
    {
        $updateData = [
            'fields' => $context->newFields,
            'indexes' => $context->newIndexes,
            'options' => $context->rawData['options'] ?? $context->collection->options ?? [],
            'api_rules' => $context->rawData['api_rules'] ?? $context->collection->api_rules ?? [],
            'schema_updated_at' => now(),
        ];

        if (array_key_exists('name', $context->rawData) && ! empty($context->rawData['name'])) {
            $updateData['name'] = $context->rawData['name'];
        }

        if (array_key_exists('description', $context->rawData)) {
            $updateData['description'] = $context->rawData['description'];
        }

        $context->collection->update($updateData);

        $context->collection->refresh();

        return $next($context);
    }
}
