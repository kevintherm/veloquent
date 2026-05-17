<?php

namespace Veloquent\Core\Support\Database;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SchemaCache
{
    /**
     * Determine if the given table exists, caching the result.
     */
    public static function hasTable(string $table): bool
    {
        $cacheKey = "velo:table_exists:{$table}";
        
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return (bool) $cached;
        }
        
        $exists = Schema::hasTable($table);
        
        if ($exists) {
            Cache::put($cacheKey, true, 86400); // 24 hours
        }
        
        return $exists;
    }

    /**
     * Clear the cache for the given table.
     */
    public static function forget(string $table): void
    {
        Cache::forget("velo:table_exists:{$table}");
    }
}
