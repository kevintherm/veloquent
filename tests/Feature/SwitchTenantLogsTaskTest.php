<?php

use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Multitenancy\Tasks\SwitchTenantFilesystemTask;
use App\Infrastructure\Multitenancy\Tasks\SwitchTenantLogsTask;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config()->set('logging.channels.daily.path', storage_path('logs/laravel.log'));
    config()->set('logging.channels.emergency.path', storage_path('logs/laravel.log'));
    config()->set('logging.channels.single.path', storage_path('logs/laravel.log'));
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('tenants/1001'));
    File::deleteDirectory(storage_path('tenants/1002'));
});

it('switches daily and emergency log paths to tenant logs while leaving single on landlord path', function () {
    $task = new SwitchTenantLogsTask;

    $tenant = new Tenant;
    $tenant->forceFill(['id' => 1001]);

    $task->makeCurrent($tenant);

    expect(config('logging.channels.daily.path'))->toBe(storage_path('tenants/1001/logs/laravel.log'))
        ->and(config('logging.channels.emergency.path'))->toBe(storage_path('tenants/1001/logs/laravel.log'))
        ->and(config('logging.channels.single.path'))->toBe(storage_path('logs/laravel.log'))
        ->and(is_dir(storage_path('tenants/1001/logs')))->toBeTrue();
});

it('restores original log paths when tenant context is forgotten', function () {
    $task = new SwitchTenantLogsTask;

    $tenant = new Tenant;
    $tenant->forceFill(['id' => 1001]);

    $task->makeCurrent($tenant);
    $task->forgetCurrent();

    expect(config('logging.channels.daily.path'))->toBe(storage_path('logs/laravel.log'))
        ->and(config('logging.channels.emergency.path'))->toBe(storage_path('logs/laravel.log'))
        ->and(config('logging.channels.single.path'))->toBe(storage_path('logs/laravel.log'));
});

it('switches log paths across tenants and still restores the baseline paths', function () {
    $task = new SwitchTenantLogsTask;

    $firstTenant = new Tenant;
    $firstTenant->forceFill(['id' => 1001]);

    $secondTenant = new Tenant;
    $secondTenant->forceFill(['id' => 1002]);

    $task->makeCurrent($firstTenant);

    expect(config('logging.channels.daily.path'))->toBe(storage_path('tenants/1001/logs/laravel.log'));

    $task->makeCurrent($secondTenant);

    expect(config('logging.channels.daily.path'))->toBe(storage_path('tenants/1002/logs/laravel.log'))
        ->and(config('logging.channels.single.path'))->toBe(storage_path('logs/laravel.log'));

    $task->forgetCurrent();

    expect(config('logging.channels.daily.path'))->toBe(storage_path('logs/laravel.log'))
        ->and(config('logging.channels.emergency.path'))->toBe(storage_path('logs/laravel.log'));
});

it('registers filesystem switch task before logs switch task', function () {
    $switchTasks = config('multitenancy.switch_tenant_tasks');

    $filesystemIndex = array_search(SwitchTenantFilesystemTask::class, $switchTasks, true);
    $logsIndex = array_search(SwitchTenantLogsTask::class, $switchTasks, true);

    expect($filesystemIndex)->not->toBeFalse()
        ->and($logsIndex)->not->toBeFalse()
        ->and($filesystemIndex)->toBeLessThan($logsIndex);
});
