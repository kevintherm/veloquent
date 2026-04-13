<?php

namespace App\Console\Commands;

use App\Infrastructure\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PurgeTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:purge {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all tenants and purge all tenant databases';

    public function handle(): int
    {
        $tenants = Tenant::query()->get();
        $orphanDatabases = $this->findOrphanDatabases($tenants);

        if ($tenants->isEmpty() && $orphanDatabases->isEmpty()) {
            $this->info('No tenants or orphan databases to purge.');

            return self::SUCCESS;
        }

        if (! $tenants->isEmpty()) {
            $this->displayTenants($tenants);
        }

        if (! $orphanDatabases->isEmpty()) {
            $this->displayOrphanDatabases($orphanDatabases);
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to delete all these tenants'.($orphanDatabases->isNotEmpty() ? ' and orphan databases' : '').'?', false)) {
                $this->info('Purge canceled.');

                return self::SUCCESS;
            }

            if (! $this->confirm('This action cannot be undone. Are you absolutely sure?', false)) {
                $this->info('Purge canceled.');

                return self::SUCCESS;
            }
        }

        $failed = 0;
        foreach ($tenants as $tenant) {
            try {
                $this->deleteTenantDatabase($tenant);
                $tenant->delete();
                $this->line("✓ Deleted: {$tenant->name} ({$tenant->database})");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("✗ Failed to delete {$tenant->name}: {$exception->getMessage()}");
                Log::error('Tenant purge failed for tenant.', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($orphanDatabases as $databaseName) {
            try {
                $this->deleteOrphanDatabase($databaseName);
                $this->line("✓ Deleted orphan database: {$databaseName}");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("✗ Failed to delete orphan database {$databaseName}: {$exception->getMessage()}");
                Log::error('Orphan database deletion failed.', [
                    'database' => $databaseName,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->line('');

        if ($failed > 0) {
            $successful = ($tenants->count() + $orphanDatabases->count()) - $failed;
            $this->warn("Purge completed with {$failed} failure(s). {$successful} database(s) deleted successfully.");

            return self::FAILURE;
        }

        $totalCount = $tenants->count() + $orphanDatabases->count();
        $this->info("All {$totalCount} database(s) purged successfully.");

        return self::SUCCESS;
    }

    private function displayTenants($tenants): void
    {
        $this->line('');
        $this->info('The following tenants will be deleted:');
        $this->line('');

        foreach ($tenants as $tenant) {
            $this->line("  • {$tenant->name}");
            $this->line("    - id: {$tenant->id}");
            $this->line("    - domain: {$tenant->domain}");
            $this->line("    - database: {$tenant->database}");
        }

        $this->line('');
    }

    private function displayOrphanDatabases($orphanDatabases): void
    {
        $this->line('');
        $this->warn('The following orphan databases will be deleted:');
        $this->line('');

        foreach ($orphanDatabases as $databaseName) {
            $this->line("  • {$databaseName}");
        }

        $this->line('');
    }

    private function findOrphanDatabases($tenants)
    {
        $tenantDatabases = $tenants->pluck('database')->toArray();
        $driver = (string) (config('database.connections.tenant.driver') ?? 'sqlite');

        if ($driver === 'sqlite') {
            return $this->findOrphanSqliteDatabases($tenantDatabases);
        }

        return $this->findOrphanSqlDatabases($tenantDatabases);
    }

    private function findOrphanSqliteDatabases($tenantDatabases)
    {
        $tenantsDir = database_path('tenants');

        if (! File::isDirectory($tenantsDir)) {
            return collect();
        }

        $files = File::files($tenantsDir);
        $orphans = collect();

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            // Skip non-SQLite files
            if (! str_ends_with($filePath, '.sqlite')) {
                continue;
            }

            // Check if this file is tracked in tenants table
            if (! in_array($filePath, $tenantDatabases, true)) {
                $orphans->push($filePath);
            }
        }

        return $orphans;
    }

    private function findOrphanSqlDatabases($tenantDatabases)
    {
        $landlordConnectionName = config('multitenancy.landlord_database_connection_name')
            ?? config('database.default');

        $databaseConnections = config('database.connections', []);
        $landlordConnectionConfig = $databaseConnections[$landlordConnectionName] ?? null;

        if (! is_array($landlordConnectionConfig)) {
            return collect();
        }

        $driver = (string) ($landlordConnectionConfig['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            return collect();
        }

        try {
            $prefix = config('velo.tenants_database_prefix', 'velo_tenant_');

            // Get all databases
            $databases = DB::connection($landlordConnectionName)
                ->select($this->getDatabasesQuery($driver));

            $databaseNames = collect($databases)
                ->pluck('Database') // MySQL/MariaDB column name
                ->concat(
                    collect($databases)->pluck('datname') // PostgreSQL column name
                )
                ->filter()
                ->toArray();

            // Find databases that match the prefix but aren't tracked
            return collect($databaseNames)->filter(function ($dbName) use ($prefix, $tenantDatabases) {
                return str_starts_with($dbName, $prefix) && ! in_array($dbName, $tenantDatabases, true);
            });
        } catch (Throwable $exception) {
            Log::warning('Failed to find orphan databases.', [
                'error' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    private function getDatabasesQuery(string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => 'SHOW DATABASES',
            'pgsql' => "SELECT datname as \"datname\" FROM pg_database WHERE datistemplate = false AND datname NOT IN ('postgres', 'template0', 'template1')",
            default => 'SHOW DATABASES',
        };
    }

    private function deleteOrphanDatabase(string $databaseName): void
    {
        $landlordConnectionName = config('multitenancy.landlord_database_connection_name')
            ?? config('database.default');

        $databaseConnections = config('database.connections', []);
        $landlordConnectionConfig = $databaseConnections[$landlordConnectionName] ?? null;

        if (! is_array($landlordConnectionConfig)) {
            throw new RuntimeException("Database connection [{$landlordConnectionName}] is not defined.");
        }

        $driver = (string) ($landlordConnectionConfig['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException("Unsupported driver [{$driver}] for orphan database deletion.");
        }

        try {
            if ($driver === 'sqlite') {
                if ($databaseName !== ':memory:' && File::exists($databaseName)) {
                    File::delete($databaseName);
                }

                return;
            }

            Schema::connection($landlordConnectionName)->dropDatabaseIfExists($databaseName);
        } catch (Throwable $exception) {
            Log::warning('Orphan database deletion failed.', [
                'database' => $databaseName,
                'driver' => $driver,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException("Failed to delete database [{$databaseName}]: {$exception->getMessage()}", previous: $exception);
        }
    }

    private function deleteTenantDatabase(Tenant $tenant): void
    {
        $databaseName = $tenant->database;

        if (! is_string($databaseName) || $databaseName === '') {
            throw new RuntimeException('Tenant database name is empty.');
        }

        $landlordConnectionName = config('multitenancy.landlord_database_connection_name')
            ?? config('database.default');

        if (! is_string($landlordConnectionName) || $landlordConnectionName === '') {
            throw new RuntimeException('Missing landlord database connection configuration.');
        }

        $databaseConnections = config('database.connections', []);

        if (! is_array($databaseConnections)) {
            throw new RuntimeException('Invalid database connection configuration.');
        }

        $landlordConnectionConfig = $databaseConnections[$landlordConnectionName] ?? null;

        if (! is_array($landlordConnectionConfig)) {
            throw new RuntimeException("Database connection [{$landlordConnectionName}] is not defined.");
        }

        $tenantConnectionName = config('multitenancy.tenant_database_connection_name')
            ?? 'tenant';

        if (! is_string($tenantConnectionName) || $tenantConnectionName === '') {
            throw new RuntimeException('Missing tenant database connection configuration.');
        }

        $tenantConnectionConfig = $databaseConnections[$tenantConnectionName] ?? null;

        if (! is_array($tenantConnectionConfig)) {
            $tenantConnectionConfig = $landlordConnectionConfig;
        }

        $driver = (string) ($tenantConnectionConfig['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException("Unsupported driver [{$driver}] for tenant deletion.");
        }

        try {
            DB::purge($tenantConnectionName);

            if ($driver === 'sqlite') {
                if ($databaseName !== ':memory:' && File::exists($databaseName)) {
                    File::delete($databaseName);
                }

                return;
            }

            Schema::connection($landlordConnectionName)->dropDatabaseIfExists($databaseName);
        } catch (Throwable $exception) {
            Log::warning('Tenant database deletion failed during purge.', [
                'database' => $databaseName,
                'driver' => $driver,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException("Failed to delete database [{$databaseName}]: {$exception->getMessage()}", previous: $exception);
        }
    }
}
