<?php

namespace App\Infrastructure\Multitenancy\Tasks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class SwitchTenantDatabaseTask implements SwitchTenantTask
{
    use UsesMultitenancyConfig;

    protected ?string $landlordDatabaseName;

    public function __construct()
    {
        $this->landlordDatabaseName = config("database.connections.{$this->tenantDatabaseConnectionName()}.database");
    }

    public function makeCurrent(IsTenant $tenant): void
    {
        $databaseName = $tenant->getDatabaseName();

        if ($databaseName === null && app()->runningUnitTests()) {
            return;
        }

        $this->setTenantConnectionDatabaseName($databaseName);
    }

    public function forgetCurrent(): void
    {
        $this->setTenantConnectionDatabaseName($this->landlordDatabaseName);
    }

    protected function setTenantConnectionDatabaseName(?string $databaseName): void
    {
        $tenantConnectionName = $this->tenantDatabaseConnectionName();

        if ($databaseName === config("database.connections.{$tenantConnectionName}.database")) {
            return;
        }

        config([
            "database.connections.{$tenantConnectionName}.database" => $databaseName,
        ]);

        app('db')->extend($tenantConnectionName, function ($config, $name) use ($databaseName) {
            $config['database'] = $databaseName;

            return app('db.factory')->make($config, $name);
        });

        DB::purge($tenantConnectionName);

        // Octane will have an old `db` instance in the Model::$resolver.
        Model::setConnectionResolver(app('db'));
    }
}
