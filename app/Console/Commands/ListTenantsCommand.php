<?php

namespace App\Console\Commands;

use App\Infrastructure\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ListTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all tenants in the system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $landlordConnectionName = $this->resolveLandlordConnectionName();

        if ($landlordConnectionName === null) {
            return self::FAILURE;
        }

        if (! $this->ensureTenantsTableExists($landlordConnectionName)) {
            return self::FAILURE;
        }

        $tenants = Tenant::all(['id', 'name', 'domain', 'database', 'created_at']);

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Domain', 'Database', 'Created At'],
            $tenants->toArray()
        );

        return self::SUCCESS;
    }

    private function resolveLandlordConnectionName(): ?string
    {
        $landlordConnectionName = config('multitenancy.landlord_database_connection_name');

        if (! is_string($landlordConnectionName) || $landlordConnectionName === '') {
            $this->error('Missing multitenancy.landlord_database_connection_name configuration.');

            return null;
        }

        if (config("database.connections.{$landlordConnectionName}") === null) {
            $this->error("Database connection [{$landlordConnectionName}] is not defined.");

            return null;
        }

        return $landlordConnectionName;
    }

    private function ensureTenantsTableExists(string $landlordConnectionName): bool
    {
        if (Schema::connection($landlordConnectionName)->hasTable('tenants')) {
            return true;
        }

        $this->error("The [tenants] table does not exist on the [{$landlordConnectionName}] connection.");

        return false;
    }
}
