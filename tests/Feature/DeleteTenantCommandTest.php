<?php

use App\Infrastructure\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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

it('deletes a tenant by ID', function () {
    $tenant = Tenant::withoutEvents(function (): Tenant {
        return Tenant::query()->create([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'database' => 'velo_tenant_test',
        ]);
    });

    artisan('tenants:delete', [
        'identifier' => (string) $tenant->id,
        '--force' => true,
    ])->assertSuccessful();

    expect(Tenant::query()->count())->toBe(0);
});

it('deletes a tenant by domain', function () {
    $tenant = Tenant::withoutEvents(function (): Tenant {
        return Tenant::query()->create([
            'name' => 'Domain Test Tenant',
            'domain' => 'domain-test.localhost',
            'database' => 'velo_tenant_domain_test',
        ]);
    });

    artisan('tenants:delete', [
        'identifier' => 'domain-test.localhost',
        '--force' => true,
    ])->assertSuccessful();

    expect(Tenant::query()->count())->toBe(0);
});

it('deletes a tenant by database name', function () {
    $tenant = Tenant::withoutEvents(function (): Tenant {
        return Tenant::query()->create([
            'name' => 'Database Test Tenant',
            'domain' => 'db-test.localhost',
            'database' => 'velo_tenant_db_test',
        ]);
    });

    artisan('tenants:delete', [
        'identifier' => 'velo_tenant_db_test',
        '--force' => true,
    ])->assertSuccessful();

    expect(Tenant::query()->count())->toBe(0);
});

it('fails when tenant is not found', function () {
    artisan('tenants:delete', [
        'identifier' => 'nonexistent',
        '--force' => true,
    ])->assertExitCode(Command::FAILURE);

    expect(Tenant::query()->count())->toBe(0);
});

it('fails when identifier is empty', function () {
    artisan('tenants:delete', [
        'identifier' => '',
        '--force' => true,
    ])->assertExitCode(Command::FAILURE);
});

it('deletes a SQLite tenant database file', function () {
    $tenantDriver = (string) config('database.connections.tenant.driver');
    if ($tenantDriver !== 'sqlite') {
        $this->markTestSkipped('SQLite driver not configured');
    }

    $databasePath = database_path('tenants/velo_tenant_sqlite_test.sqlite');

    $tenant = Tenant::query()->create([
        'name' => 'SQLite Test Tenant',
        'domain' => 'sqlite-test.localhost',
        'database' => $databasePath,
    ]);

    // Manually create the SQLite file since we're not running the observer
    File::ensureDirectoryExists(dirname($databasePath));
    File::put($databasePath, '');

    expect(File::exists($databasePath))->toBeTrue();

    artisan('tenants:delete', [
        'identifier' => (string) $tenant->id,
        '--force' => true,
    ])->assertSuccessful();

    expect(Tenant::query()->count())->toBe(0);
    expect(File::exists($databasePath))->toBeFalse();
});
