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
        return Cache::remember(
            "velo:table_exists:{$table}",
            86400, // 24 hours
            fn () => Schema::hasTable($table)
        );
    }

    /**
     * Clear the cache for the given table.
     */
    public static function forget(string $table): void
    {
        Cache::forget("velo:table_exists:{$table}");
    }
}
