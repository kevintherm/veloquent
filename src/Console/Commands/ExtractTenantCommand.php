<?php

namespace Veloquent\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Veloquent\Core\Support\Models\Tenant;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Throwable;

class ExtractTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:extract {--tenant= : The ID of the target tenant} {--cleanup : Delete extracted tables from landlord database after success}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract tenant data from the landlord database to a newly created tenant database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        if (!$tenantId) {
            $this->error('Please specify a target tenant ID using --tenant={id}');
            return self::FAILURE;
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant with ID [{$tenantId}] not found in the landlord database.");
            return self::FAILURE;
        }

        $this->info("Extracting data for tenant: {$tenant->name} (ID: {$tenant->id})");

        $landlordConnection = config('multitenancy.landlord_database_connection_name') ?? config('database.default');
        $tenantConnection = config('multitenancy.tenant_database_connection_name') ?? 'tenant';

        try {
            $staticTables = [
                'superusers',
                'auth_tokens',
                'otp_tokens',
                'email_templates',
                'oauth_providers',
                'oauth_accounts',
                'users',
                'settings',
            ];

            foreach ($staticTables as $table) {
                if (Schema::connection($landlordConnection)->hasTable($table)) {
                    $this->migrateTable($table, $landlordConnection, $tenantConnection);
                }
            }

            if (Schema::connection($landlordConnection)->hasTable('collections')) {
                $this->migrateTable('collections', $landlordConnection, $tenantConnection);

                $collections = DB::connection($landlordConnection)->table('collections')->get();
                foreach ($collections as $collection) {
                    $tableName = config('velo.collection_prefix', '_velo_') . $collection->name;
                    if (Schema::connection($landlordConnection)->hasTable($tableName)) {
                        $this->migrateDynamicTable($tableName, $landlordConnection, $tenantConnection);
                    }
                }
            }

            $this->info("\nData extraction completed successfully.");

            if ($this->option('cleanup')) {
                $this->cleanupLandlord($landlordConnection, $staticTables, $collections ?? collect());
            }

        } catch (Throwable $e) {
            $this->error("\nExtraction failed: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function migrateTable(string $table, string $from, string $to): void
    {
        $this->info("  Migrating table: {$table}");

        if (!Schema::connection($to)->hasTable($table)) {
            $this->warn("    Target table [{$table}] does not exist in tenant database. Skipping rows.");
            return;
        }

        DB::connection($to)->table($table)->truncate();

        DB::connection($from)->table($table)->orderBy('id')->chunk(100, function ($rows) use ($table, $to) {
            $data = collect($rows)->map(fn($row) => (array) $row)->toArray();
            DB::connection($to)->table($table)->insert($data);
        });
    }

    protected function migrateDynamicTable(string $table, string $from, string $to): void
    {
        $this->info("  Migrating dynamic collection table: {$table}");

        if (!Schema::connection($to)->hasTable($table)) {
            $this->info("    Creating table [{$table}] in tenant database...");
            
            $driver = DB::connection($from)->getDriverName();
            if ($driver === 'sqlite') {
                $schema = DB::connection($from)->selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]);
                if ($schema) {
                    DB::connection($to)->statement($schema->sql);
                }
            } else {
                $this->error("    Automatic table creation not yet implemented for [{$driver}]. Please ensure the collection exists in the tenant DB.");
                return;
            }
        }

        DB::connection($to)->table($table)->truncate();

        DB::connection($from)->table($table)->orderBy('id')->chunk(100, function ($rows) use ($table, $to) {
            $data = collect($rows)->map(fn($row) => (array) $row)->toArray();
            DB::connection($to)->table($table)->insert($data);
        });
    }

    protected function cleanupLandlord(string $connection, array $staticTables, $collections): void
    {
        $this->warn("\nCleaning up landlord database...");

        foreach ($staticTables as $table) {
            if ($this->confirm("  Delete table [{$table}] from landlord connection?", false)) {
                Schema::connection($connection)->dropIfExists($table);
            }
        }

        foreach ($collections as $collection) {
            $tableName = config('velo.collection_prefix', '_velo_') . $collection->name;
            if (Schema::connection($connection)->hasTable($tableName)) {
                if ($this->confirm("  Delete collection table [{$tableName}] from landlord connection?", false)) {
                    Schema::connection($connection)->dropIfExists($tableName);
                }
            }
        }
        
        if (Schema::connection($connection)->hasTable('collections')) {
            if ($this->confirm("  Delete [collections] table from landlord connection?", false)) {
                Schema::connection($connection)->dropIfExists('collections');
            }
        }
    }
}
