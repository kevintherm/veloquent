<?php

namespace App\Console\Commands;

use App\Infrastructure\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:tenants:create {name : The name of the tenant} {--domain= : Tenant domain (defaults to a slug-based domain)} {--database= : Tenant database name (defaults to tenant_{slug})}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert a tenant record with validated domain and database values';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $domain = $this->resolveDomain($name);
        $database = $this->resolveDatabaseName($name);
        $landlordConnectionName = $this->resolveLandlordConnectionName();

        if ($landlordConnectionName === null) {
            return self::FAILURE;
        }

        if (! $this->ensureTenantsTableExists($landlordConnectionName)) {
            return self::FAILURE;
        }

        $validator = Validator::make([
            'name' => $name,
            'domain' => $domain,
            'database' => $database,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (Tenant::query()->where('domain', $domain)->exists()) {
            $this->error("Domain [{$domain}] is already assigned to another tenant.");

            return self::FAILURE;
        }

        if (Tenant::query()->where('database', $database)->exists()) {
            $this->error("Database [{$database}] is already assigned to another tenant.");

            return self::FAILURE;
        }

        try {
            $tenant = Tenant::query()->create([
                'name' => $name,
                'domain' => $domain,
                'database' => $database,
            ]);
        } catch (\Throwable $exception) {
            $this->error("Failed to create tenant record: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Tenant [{$tenant->name}] created successfully.");
        $this->line("- id: {$tenant->id}");
        $this->line("- domain: {$tenant->domain}");
        $this->line("- database: {$tenant->database}");

        return self::SUCCESS;
    }

    private function resolveDomain(string $name): string
    {
        $providedDomain = trim((string) $this->option('domain'));

        if ($providedDomain !== '') {
            return strtolower($providedDomain);
        }

        $appUrlHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $rootHost = is_string($appUrlHost) && $appUrlHost !== '' ? $appUrlHost : 'localhost';

        return strtolower($this->tenantSlug($name, '-').'.'.$rootHost);
    }

    private function resolveDatabaseName(string $name): string
    {
        $providedDatabase = trim((string) $this->option('database'));

        if ($providedDatabase !== '') {
            return $providedDatabase;
        }

        return 'velo_tenant_'.$this->tenantSlug($name, '_');
    }

    private function tenantSlug(string $name, string $separator): string
    {
        $slug = Str::of($name)->slug($separator)->toString();

        if ($slug === '') {
            return 'tenant';
        }

        return $slug;
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
        $this->line('Run: vendor/bin/sail artisan migrate --database=landlord --path=database/migrations/landlord --no-interaction');

        return false;
    }
}
