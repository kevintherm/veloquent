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
    $tenantDriver = (string) config('database.connections.tenant.driver');
    $uniqueName = 'Acme Team '.microtime(true);

    artisan('tenants:create', [
        'name' => $uniqueName,
    ])->assertSuccessful();

    $tenant = Tenant::query()->firstOrFail();
    $appUrlHost = parse_url((string) config('app.url'), PHP_URL_HOST);
    $rootHost = is_string($appUrlHost) && $appUrlHost !== '' ? $appUrlHost : 'localhost';

    expect($tenant->name)->toBe($uniqueName);
    expect($tenant->domain)->toContain('localhost');
    expect($tenant->database)->toContain('velo_tenant_');

    config(['database.connections.tenant.database' => $tenant->database]);
    DB::purge('tenant');

    $migrationsTable = (string) config('database.migrations.table', 'migrations');
    expect(Schema::connection('tenant')->hasTable($migrationsTable))->toBeTrue();
});

it('fails when the tenant domain already exists', function () {
    $uniqueDomain = 'existing-'.microtime(true).'.localhost';
    Tenant::withoutEvents(function () use ($uniqueDomain): void {
        Tenant::query()->create([
            'name' => 'Existing Tenant',
            'domain' => $uniqueDomain,
            'database' => 'tenant_existing_'.str_replace('.', '_', $uniqueDomain),
        ]);
    });

    artisan('tenants:create', [
        'name' => 'Another Tenant',
        '--domain' => $uniqueDomain,
        '--database' => 'tenant_another',
    ])->assertExitCode(Command::FAILURE);

    expect(Tenant::query()->count())->toBe(1);
});

it('does not insert a tenant row when tenant migrations fail', function () {
    ArtisanFacade::shouldReceive('call')->once()->andReturn(1);

    artisan('tenants:create', [
        'name' => 'Broken Tenant '.microtime(true),
    ])->assertExitCode(Command::FAILURE);

    expect(Tenant::query()->count())->toBe(0);
});
