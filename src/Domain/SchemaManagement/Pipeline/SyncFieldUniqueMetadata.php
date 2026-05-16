<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\Collections\Enums\IndexType;
use Closure;

class SyncFieldUniqueMetadata
{
    public function handle(SyncContext $context, Closure $next)
    {
        $uniqueColumns = collect($context->newIndexes)
            ->filter(function ($index) {
                $type = is_array($index) ? ($index['type'] ?? null) : $index->type;
                $columns = is_array($index) ? ($index['columns'] ?? []) : $index->columns;
                return $type === IndexType::Unique->value && count($columns) === 1;
            })
            ->map(function ($index) {
                $columns = is_array($index) ? ($index['columns'] ?? []) : $index->columns;
                return $columns[0];
            })
            ->all();

        $context->newFields = collect($context->newFields)->map(function ($field) use ($uniqueColumns) {
            $data = is_object($field) ? $field->toArray() : (array) $field;
            $name = $data['name'] ?? null;

            if ($name === 'id') {
                $data['unique'] = true;
            } else {
                $data['unique'] = in_array($name, $uniqueColumns, true);
            }
            
            return $data;
        })->all();


        return $next($context);
    }
}
