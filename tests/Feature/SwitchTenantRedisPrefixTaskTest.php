<?php

use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Multitenancy\Tasks\SwitchTenantRedisPrefixTask;

beforeEach(function (): void {
    config()->set('database.redis.options.prefix', 'velo-database-');
});

it('switches redis prefix to tenant specific value', function () {
    $task = new SwitchTenantRedisPrefixTask;

    $tenant = new Tenant;
    $tenant->forceFill(['id' => 1001]);

    $task->makeCurrent($tenant);

    expect(config('database.redis.options.prefix'))->toBe('velo-database-tenant_1001_');
});

it('restores original redis prefix when tenant context is forgotten', function () {
    $task = new SwitchTenantRedisPrefixTask;

    $tenant = new Tenant;
    $tenant->forceFill(['id' => 1001]);

    $task->makeCurrent($tenant);
    $task->forgetCurrent();

    expect(config('database.redis.options.prefix'))->toBe('velo-database-');
});

it('switches between tenants and still restores baseline prefix', function () {
    $task = new SwitchTenantRedisPrefixTask;

    $firstTenant = new Tenant;
    $firstTenant->forceFill(['id' => 1001]);

    $secondTenant = new Tenant;
    $secondTenant->forceFill(['id' => 1002]);

    $task->makeCurrent($firstTenant);
    expect(config('database.redis.options.prefix'))->toBe('velo-database-tenant_1001_');

    $task->makeCurrent($secondTenant);
    expect(config('database.redis.options.prefix'))->toBe('velo-database-tenant_1002_');

    $task->forgetCurrent();
    expect(config('database.redis.options.prefix'))->toBe('velo-database-');
});

it('is registered as a multitenancy switch task', function () {
    expect(config('multitenancy.switch_tenant_tasks'))->toContain(SwitchTenantRedisPrefixTask::class);
});
