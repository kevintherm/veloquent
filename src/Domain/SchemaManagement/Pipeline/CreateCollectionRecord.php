<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;
use Closure;

class CreateCollectionRecord
{
    public function handle(SyncContext $context, Closure $next)
    {
        if (! $context->isCreate()) {
            return $next($context);
        }

        $data = $context->rawData;
        $isAuthCollection = ($data['type'] ?? '') === 'auth';
        
        $mergedFields = SchemaChange::mergeWithSystemFields($data['fields'], $isAuthCollection);
        $tableName = SchemaChange::generateTableName($data['name'], $data['is_system'] ?? false);

        $context->collection = Collection::create([
            'is_system' => $data['is_system'] ?? false,
            ...$data,
            'table_name' => $tableName,
            'fields' => $mergedFields,
            'indexes' => $data['indexes'] ?? [],
        ]);
        
        $context->newFields = $mergedFields;

        return $next($context);
    }
}
