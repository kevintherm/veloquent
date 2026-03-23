<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Models\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrphanTableService
{
    private string $prefix;

    public function __construct()
    {
        $this->prefix = config('velo.collection_prefix', '_velo_');
    }

    /**
     * Get a list of database tables that are not linked to any collection.
     *
     * @return array<int, string>
     */
    public function getOrphans(): array
    {
        $allTables = Schema::getTableListing();
        $prefix = $this->prefix;

        $collectionTables = Collection::pluck('table_name')->all();

        // @TODO: Relocate this to a config file for easier management on the future
        $systemTables = [
            'collections',
            'schema_jobs',
            'failed_jobs',
            'job_batches',
            'jobs',
            'migrations',
            'password_reset_tokens',
            'realtime_subscriptions',
            'superusers',
            'cache',
            'cache_locks',
            'auth_tokens',
        ];

        return collect($allTables)
            ->map(fn (string $table) => str_contains($table, '.') ? Str::afterLast($table, '.') : $table)
            ->filter(fn (string $table) => Str::startsWith($table, $prefix))
            ->reject(fn (string $table) => in_array($table, $systemTables))
            ->reject(fn (string $table) => in_array($table, $collectionTables))
            ->values()
            ->all();
    }

    /**
     * Drop a single orphan table.
     */
    public function dropTable(string $tableName): bool
    {
        if (! $this->isOrphan($tableName)) {
            return false;
        }

        Schema::dropIfExists($tableName);

        return true;
    }

    public function isOrphan(string $tableName): bool
    {
        $tableName = str_contains($tableName, '.') ? Str::afterLast($tableName, '.') : $tableName;

        if (! str_starts_with($tableName, $this->prefix)) {
            return false;
        }

        $allTables = Collection::pluck('table_name')->filter()->toArray();

        return ! in_array($tableName, $allTables);
    }

    /**
     * Drop all orphan tables.
     */
    public function dropAllOrphans(): void
    {
        foreach ($this->getOrphans() as $tableName) {
            $this->dropTable($tableName);
        }
    }
}
