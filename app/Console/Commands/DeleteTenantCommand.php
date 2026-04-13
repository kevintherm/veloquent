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

class DeleteTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:delete {identifier : Tenant ID, domain, or database name} {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a tenant and purge its database';

    public function handle(): int
    {
        $identifier = trim((string) $this->argument('identifier'));

        if ($identifier === '') {
            $this->error('Tenant identifier is required.');

            return self::FAILURE;
        }

        $tenant = $this->findTenant($identifier);

        if ($tenant === null) {
            $this->error("Tenant [{$identifier}] not found.");

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->info("About to delete tenant: {$tenant->name}");
            $this->line("- id: {$tenant->id}");
            $this->line("- domain: {$tenant->domain}");
            $this->line("- database: {$tenant->database}");
            $this->line('');

            if (! $this->confirm('Are you sure you want to delete this tenant and purge its database?', false)) {
                $this->info('Deletion canceled.');

                return self::SUCCESS;
            }
        }

        try {
            $this->deleteTenantDatabase($tenant);
            $tenant->delete();
            $this->info("Tenant [{$tenant->name}] deleted successfully.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error("Failed to delete tenant: {$exception->getMessage()}");
            Log::error('Tenant deletion failed.', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'error' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    private function findTenant(string $identifier): ?Tenant
    {
        $tenant = Tenant::query()->find($identifier);
        if ($tenant !== null) {
            return $tenant;
        }

        $tenant = Tenant::query()->where('domain', $identifier)->first();
        if ($tenant !== null) {
            return $tenant;
        }

        return Tenant::query()->where('database', $identifier)->first();
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
            Log::warning('Tenant database deletion failed.', [
                'database' => $databaseName,
                'driver' => $driver,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException("Failed to delete database [{$databaseName}]: {$exception->getMessage()}", previous: $exception);
        }
    }
}
