<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Support\TableName;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;

class CreateCollectionRecord
{
    public function handle(SyncContext $context, Closure $next)
    {
        if (! $context->isCreate()) {
            return $next($context);
        }

        $data = $context->rawData;
        $collectionType = $data['type'] ?? null;
        if ($collectionType instanceof \Veloquent\Core\Domain\Collections\Enums\CollectionType) {
            $collectionType = $collectionType->value;
        }

        $mergedFields = SchemaChange::mergeWithSystemFields($data['fields'], $collectionType);
        $tableName = TableName::for($data['name'], $data['is_system'] ?? false);

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
