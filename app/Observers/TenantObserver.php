<?php

namespace App\Observers;

use App\Infrastructure\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class TenantObserver
{
    public function creating(Tenant $tenant): void
    {
        $this->prepareTenantDatabaseAndRunMigrations($tenant);
    }

    private function prepareTenantDatabaseAndRunMigrations(Tenant $tenant): void
    {
        $databaseName = $tenant->database;

        if (! is_string($databaseName) || $databaseName === '') {
            throw new RuntimeException('Tenant database name is required.');
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
            config(["database.connections.{$tenantConnectionName}" => $tenantConnectionConfig]);
        }

        $driver = (string) ($tenantConnectionConfig['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException("Unsupported driver [{$driver}] for tenant provisioning.");
        }

        $tenantDatabase = $this->resolveTenantDatabaseName($databaseName, $driver);
        $tenant->database = $tenantDatabase;

        $originalTenantDatabase = $tenantConnectionConfig['database'] ?? null;
        $databaseWasCreated = false;

        try {
            $databaseWasCreated = $this->createTenantDatabase(
                landlordConnectionName: $landlordConnectionName,
                landlordConnectionConfig: $landlordConnectionConfig,
                driver: $driver,
                databaseName: $tenantDatabase,
            );

            $this->runTenantMigrations(
                tenantConnectionName: $tenantConnectionName,
                tenantDatabase: $tenantDatabase,
            );
        } catch (Throwable $exception) {
            $this->rollbackProvisionedDatabase(
                landlordConnectionName: $landlordConnectionName,
                driver: $driver,
                databaseName: $tenantDatabase,
                databaseWasCreated: $databaseWasCreated,
                tenantConnectionName: $tenantConnectionName,
            );

            throw new RuntimeException(
                "Tenant provisioning failed for [{$tenantDatabase}]: {$exception->getMessage()}",
                previous: $exception,
            );
        } finally {
            config(["database.connections.{$tenantConnectionName}.database" => $originalTenantDatabase]);
            DB::purge($tenantConnectionName);
        }
    }

    private function resolveTenantDatabaseName(string $databaseName, string $driver): string
    {
        if ($driver !== 'sqlite') {
            return $databaseName;
        }

        if ($databaseName === ':memory:') {
            return $databaseName;
        }

        if (str_contains($databaseName, '/') || str_ends_with($databaseName, '.sqlite')) {
            if (str_starts_with($databaseName, '/')) {
                return $databaseName;
            }

            return database_path($databaseName);
        }

        return database_path('tenants/'.$databaseName.'.sqlite');
    }

    private function createTenantDatabase(
        string $landlordConnectionName,
        array $landlordConnectionConfig,
        string $driver,
        string $databaseName,
    ): bool {
        if ($driver === 'sqlite') {
            if ($databaseName === ':memory:') {
                return false;
            }

            $directory = dirname($databaseName);
            File::ensureDirectoryExists($directory);

            if (File::exists($databaseName)) {
                return false;
            }

            File::put($databaseName, '');

            return true;
        }

        try {
            Schema::connection($landlordConnectionName)->createDatabase($databaseName);

            return true;
        } catch (Throwable $exception) {
            if ($this->databaseAlreadyExists($exception, $driver)) {
                return false;
            }

            throw new RuntimeException("Failed to create database [{$databaseName}]: {$exception->getMessage()}", previous: $exception);
        }
    }

    private function runTenantMigrations(string $tenantConnectionName, string $tenantDatabase): void
    {
        config(["database.connections.{$tenantConnectionName}.database" => $tenantDatabase]);
        DB::purge($tenantConnectionName);

        $exitCode = Artisan::call('migrate', [
            '--database' => $tenantConnectionName,
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Tenant migration command returned a non-zero exit code.');
        }

        $migrationsTable = (string) config('database.migrations.table', 'migrations');

        if (! Schema::connection($tenantConnectionName)->hasTable($migrationsTable)) {
            throw new RuntimeException("Tenant migrations did not create the [{$migrationsTable}] table.");
        }
    }

    private function rollbackProvisionedDatabase(
        string $landlordConnectionName,
        string $driver,
        string $databaseName,
        bool $databaseWasCreated,
        string $tenantConnectionName,
    ): void {
        if (! $databaseWasCreated) {
            return;
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
        } catch (Throwable $cleanupException) {
            Log::warning('Tenant provisioning cleanup failed.', [
                'database' => $databaseName,
                'driver' => $driver,
                'message' => $cleanupException->getMessage(),
            ]);
        }
    }

    private function databaseAlreadyExists(Throwable $exception, string $driver): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $sqlState === 'HY000' && $driverCode === '1007';
        }

        if ($driver === 'pgsql') {
            return $sqlState === '42P04';
        }

        return false;
    }
}
