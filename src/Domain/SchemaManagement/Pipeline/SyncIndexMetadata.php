<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Closure;

class SyncIndexMetadata
{
    public function handle(SyncContext $context, Closure $next)
    {
        if (!$context->schemaChange) {
            return $next($context);
        }

        $renames = collect($context->schemaChange->renames)->pluck(1, 0)->all();
        $drops = collect($context->schemaChange->drops)->pluck('name')->all();

        $context->newIndexes = collect($context->newIndexes)->map(function ($index) use ($renames, $drops) {
            $isObject = !is_array($index);
            $columns = $isObject ? $index->columns : $index['columns'];
            
            $newColumns = [];
            foreach ($columns as $col) {
                if (in_array($col, $drops, true)) {
                    return null; // Drop the index if any column is dropped
                }
                $newColumns[] = $renames[$col] ?? $col;
            }
            
            if ($isObject) {
                $index->columns = $newColumns;
            } else {
                $index['columns'] = $newColumns;
            }
            
            return $index;
        })->filter()->values()->all();

        return $next($context);
    }
}
