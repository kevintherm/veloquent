<?php

use App\Infrastructure\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan as ArtisanFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $landlordConnection = (string) config('multitenancy.landlord_database_connection_name', 'landlord');

    if (Schema::connection($landlordConnection)->hasTable('tenants')) {
        Schema::connection($landlordConnection)->drop('tenants');
    }

    Schema::connection($landlordConnection)->create('tenants', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('domain')->unique();
        $table->string('database')->unique();
        $table->timestamps();
    });
});

it('creates a tenant using generated domain and database names', function () {
    artisan('tenants:create', [
        'name' => 'Acme Team',
    ])->assertSuccessful();

    $tenant = Tenant::query()->firstOrFail();
    $tenantDriver = (string) config('database.connections.tenant.driver');
    $appUrlHost = parse_url((string) config('app.url'), PHP_URL_HOST);
    $rootHost = is_string($appUrlHost) && $appUrlHost !== '' ? $appUrlHost : 'localhost';

    expect($tenant->name)->toBe('Acme Team');
    expect($tenant->domain)->toBe('acme-team.'.$rootHost);
    if ($tenantDriver === 'sqlite') {
        expect($tenant->database)->toBe(database_path('tenants/velo_tenant_acme_team.sqlite'));
    } else {
        expect($tenant->database)->toBe('velo_tenant_acme_team');
    }

    config(['database.connections.tenant.database' => $tenant->database]);
    DB::purge('tenant');

    $migrationsTable = (string) config('database.migrations.table', 'migrations');
    expect(Schema::connection('tenant')->hasTable($migrationsTable))->toBeTrue();
});

it('fails when the tenant domain already exists', function () {
    Tenant::withoutEvents(function (): void {
        Tenant::query()->create([
            'name' => 'Existing Tenant',
            'domain' => 'existing.localhost',
            'database' => 'tenant_existing',
        ]);
    });

    artisan('tenants:create', [
        'name' => 'Another Tenant',
        '--domain' => 'existing.localhost',
        '--database' => 'tenant_another',
    ])->assertExitCode(Command::FAILURE);

    expect(Tenant::query()->count())->toBe(1);
});

it('does not insert a tenant row when tenant migrations fail', function () {
    ArtisanFacade::shouldReceive('call')->once()->andReturn(1);

    artisan('tenants:create', [
        'name' => 'Broken Tenant',
    ])->assertExitCode(Command::FAILURE);

    expect(Tenant::query()->count())->toBe(0);
});
