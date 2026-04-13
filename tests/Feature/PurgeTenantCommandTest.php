<?php

use App\Infrastructure\Models\Tenant;
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

it('purges all tenants with force flag', function () {
    Tenant::withoutEvents(function (): void {
        Tenant::query()->create([
            'name' => 'First Tenant',
            'domain' => 'first.localhost',
            'database' => 'velo_tenant_first',
        ]);
        Tenant::query()->create([
            'name' => 'Second Tenant',
            'domain' => 'second.localhost',
            'database' => 'velo_tenant_second',
        ]);
        Tenant::query()->create([
            'name' => 'Third Tenant',
            'domain' => 'third.localhost',
            'database' => 'velo_tenant_third',
        ]);
    });

    expect(Tenant::query()->count())->toBe(3);

    artisan('tenants:purge', [
        '--force' => true,
    ])->assertSuccessful();

    expect(Tenant::query()->count())->toBe(0);
});

it('displays list of tenants to be purged', function () {
    Tenant::withoutEvents(function (): void {
        Tenant::query()->create([
            'name' => 'Test Tenant 1',
            'domain' => 'test1.localhost',
            'database' => 'velo_tenant_test1',
        ]);
        Tenant::query()->create([
            'name' => 'Test Tenant 2',
            'domain' => 'test2.localhost',
            'database' => 'velo_tenant_test2',
        ]);
    });

    artisan('tenants:purge', [
        '--force' => true,
    ])->assertSuccessful();
});

it('succeeds when no tenants exist', function () {
    expect(Tenant::query()->count())->toBe(0);

    artisan('tenants:purge', [
        '--force' => true,
    ])->assertSuccessful();
});

it('requires both confirmations before proceeding', function () {
    Tenant::withoutEvents(function (): void {
        Tenant::query()->create([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'database' => 'velo_tenant_test',
        ]);
    });

    expect(Tenant::query()->count())->toBe(1);

    artisan('tenants:purge')
        ->expectsQuestion('Are you sure you want to delete all these tenants?', 'yes')
        ->expectsQuestion('This action cannot be undone. Are you absolutely sure?', 'yes')
        ->assertSuccessful();

    expect(Tenant::query()->count())->toBe(0);
});

it('deletes SQLite tenant database files during purge', function () {
    $tenantDriver = (string) config('database.connections.tenant.driver');
    if ($tenantDriver !== 'sqlite') {
        $this->markTestSkipped('SQLite driver not configured');
    }

    $uniqueId = microtime(true) * 10000;
    $databasePath1 = database_path("tenants/velo_tenant_sqlite_purge_1_{$uniqueId}.sqlite");
    $databasePath2 = database_path("tenants/velo_tenant_sqlite_purge_2_{$uniqueId}.sqlite");

    Tenant::withoutEvents(function () use ($databasePath1, $databasePath2): void {
        Tenant::query()->create([
            'name' => 'SQLite Purge 1',
            'domain' => 'sqlite-purge-1.localhost',
            'database' => $databasePath1,
        ]);
        Tenant::query()->create([
            'name' => 'SQLite Purge 2',
            'domain' => 'sqlite-purge-2.localhost',
            'database' => $databasePath2,
        ]);
    });

    // Manually create the SQLite files
    File::ensureDirectoryExists(dirname($databasePath1));
    File::put($databasePath1, '');
    File::put($databasePath2, '');

    expect(File::exists($databasePath1))->toBeTrue();
    expect(File::exists($databasePath2))->toBeTrue();

    artisan('tenants:purge', [
        '--force' => true,
    ])->assertSuccessful();

    expect(Tenant::query()->count())->toBe(0);
    expect(File::exists($databasePath1))->toBeFalse();
    expect(File::exists($databasePath2))->toBeFalse();
});

it('detects and deletes orphan SQLite databases', function () {
    $tenantDriver = (string) config('database.connections.tenant.driver');
    if ($tenantDriver !== 'sqlite') {
        $this->markTestSkipped('SQLite driver not configured');
    }

    $uniqueId = microtime(true) * 10000;
    // Create a tracked tenant
    $trackedPath = database_path("tenants/velo_tenant_tracked_{$uniqueId}.sqlite");
    Tenant::withoutEvents(function () use ($trackedPath): void {
        Tenant::query()->create([
            'name' => 'Tracked Tenant',
            'domain' => 'tracked.localhost',
            'database' => $trackedPath,
        ]);
    });

    // Create an orphan database file (not tracked)
    $orphanPath = database_path("tenants/velo_tenant_orphan_{$uniqueId}.sqlite");
    File::ensureDirectoryExists(dirname($orphanPath));
    File::put($trackedPath, '');
    File::put($orphanPath, '');

    expect(File::exists($trackedPath))->toBeTrue();
    expect(File::exists($orphanPath))->toBeTrue();

    artisan('tenants:purge', [
        '--force' => true,
    ])->assertSuccessful();

    expect(Tenant::query()->count())->toBe(0);
    expect(File::exists($trackedPath))->toBeFalse();
    expect(File::exists($orphanPath))->toBeFalse();
});
