<?php

use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Multitenancy\Tasks\SwitchTenantFilesystemTask;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.s3', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'tenant-files',
        'url' => null,
        'endpoint' => null,
        'use_path_style_endpoint' => false,
        'throw' => false,
        'report' => false,
    ]);
    config()->set('filesystems.disks.tenant.root', storage_path('tenants/landlord/app'));
    config()->set('filesystems.disks.tenant.driver', 'local');
    config()->set('filesystems.disks.tenant.prefix', null);
    config()->set('velo.realtime.filesystem_bus_path', storage_path('realtime/bus'));
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('tenants/901'));
    File::deleteDirectory(storage_path('tenants/902'));
});

it('switches filesystem and realtime paths to tenant directories', function () {
    $task = new SwitchTenantFilesystemTask;

    $tenant = new Tenant;
    $tenant->forceFill(['id' => 901]);

    $task->makeCurrent($tenant);

    expect(config('filesystems.default'))->toBe('tenant')
        ->and(config('filesystems.disks.tenant.root'))->toBe(storage_path('tenants/901/app'))
        ->and(config('velo.realtime.filesystem_bus_path'))->toBe(storage_path('tenants/901/realtime/bus'))
        ->and(is_dir(storage_path('tenants/901/app')))->toBeTrue()
        ->and(is_dir(storage_path('tenants/901/realtime/bus')))->toBeTrue()
        ->and(is_dir(storage_path('tenants/901/logs')))->toBeTrue();
});

it('restores filesystem and realtime configuration when tenant context is forgotten', function () {
    $task = new SwitchTenantFilesystemTask;

    $tenant = new Tenant;
    $tenant->forceFill(['id' => 901]);

    $task->makeCurrent($tenant);
    $task->forgetCurrent();

    expect(config('filesystems.default'))->toBe('local')
        ->and(config('filesystems.disks.tenant.root'))->toBe(storage_path('tenants/landlord/app'))
        ->and(config('velo.realtime.filesystem_bus_path'))->toBe(storage_path('realtime/bus'));
});

it('switches paths between tenants and still restores the baseline configuration', function () {
    $task = new SwitchTenantFilesystemTask;

    $firstTenant = new Tenant;
    $firstTenant->forceFill(['id' => 901]);

    $secondTenant = new Tenant;
    $secondTenant->forceFill(['id' => 902]);

    $task->makeCurrent($firstTenant);

    expect(config('filesystems.disks.tenant.root'))->toBe(storage_path('tenants/901/app'))
        ->and(config('velo.realtime.filesystem_bus_path'))->toBe(storage_path('tenants/901/realtime/bus'));

    $task->makeCurrent($secondTenant);

    expect(config('filesystems.disks.tenant.root'))->toBe(storage_path('tenants/902/app'))
        ->and(config('velo.realtime.filesystem_bus_path'))->toBe(storage_path('tenants/902/realtime/bus'));

    $task->forgetCurrent();

    expect(config('filesystems.default'))->toBe('local')
        ->and(config('filesystems.disks.tenant.root'))->toBe(storage_path('tenants/landlord/app'))
        ->and(config('velo.realtime.filesystem_bus_path'))->toBe(storage_path('realtime/bus'));
});

it('uses s3 prefix isolation when original default disk is s3', function () {
    config()->set('filesystems.default', 's3');

    $task = new SwitchTenantFilesystemTask;

    $tenant = new Tenant;
    $tenant->forceFill(['id' => 901]);

    $task->makeCurrent($tenant);

    expect(config('filesystems.default'))->toBe('tenant')
        ->and(config('filesystems.disks.tenant.driver'))->toBe('s3')
        ->and(config('filesystems.disks.tenant.prefix'))->toBe('tenants/901/app')
        ->and(config('filesystems.disks.tenant.bucket'))->toBe('tenant-files')
        ->and(config('velo.realtime.filesystem_bus_path'))->toBe(storage_path('tenants/901/realtime/bus'));
});

it('restores original tenant disk settings after s3 tenant switching', function () {
    config()->set('filesystems.default', 's3');
    config()->set('filesystems.disks.tenant.driver', 'local');
    config()->set('filesystems.disks.tenant.prefix', null);

    $task = new SwitchTenantFilesystemTask;

    $tenant = new Tenant;
    $tenant->forceFill(['id' => 901]);

    $task->makeCurrent($tenant);
    $task->forgetCurrent();

    expect(config('filesystems.default'))->toBe('s3')
        ->and(config('filesystems.disks.tenant.driver'))->toBe('local')
        ->and(config('filesystems.disks.tenant.prefix'))->toBeNull()
        ->and(config('filesystems.disks.tenant.root'))->toBe(storage_path('tenants/landlord/app'));
});

it('updates s3 tenant prefix when switching between tenants', function () {
    config()->set('filesystems.default', 's3');

    $task = new SwitchTenantFilesystemTask;

    $firstTenant = new Tenant;
    $firstTenant->forceFill(['id' => 901]);

    $secondTenant = new Tenant;
    $secondTenant->forceFill(['id' => 902]);

    $task->makeCurrent($firstTenant);
    expect(config('filesystems.disks.tenant.prefix'))->toBe('tenants/901/app');

    $task->makeCurrent($secondTenant);
    expect(config('filesystems.disks.tenant.prefix'))->toBe('tenants/902/app');

    $task->forgetCurrent();
    expect(config('filesystems.default'))->toBe('s3');
});
