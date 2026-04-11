<?php

use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Multitenancy\Tasks\SwitchTenantAppUrlTask;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    config()->set('app.force_https', false);
    config()->set('app.url', 'http://localhost:8000');
    URL::forceScheme(null);
    URL::forceRootUrl((string) config('app.url'));
});

it('switches app url host to tenant domain while preserving scheme and port', function () {
    $task = new SwitchTenantAppUrlTask;

    $task->makeCurrent(new Tenant([
        'domain' => 'acme.localhost',
    ]));

    expect(config('app.url'))->toBe('http://acme.localhost:8000')
        ->and(url('/api/collections'))->toBe('http://acme.localhost:8000/api/collections');
});

it('restores original app url when tenant context is forgotten', function () {
    $task = new SwitchTenantAppUrlTask;

    $task->makeCurrent(new Tenant([
        'domain' => 'acme.localhost',
    ]));
    $task->forgetCurrent();

    expect(config('app.url'))->toBe('http://localhost:8000')
        ->and(url('/api/collections'))->toBe('http://localhost:8000/api/collections');
});

it('switches host across tenants and still restores the baseline app url', function () {
    $task = new SwitchTenantAppUrlTask;

    $task->makeCurrent(new Tenant([
        'domain' => 'acme.localhost',
    ]));

    expect(config('app.url'))->toBe('http://acme.localhost:8000');

    $task->makeCurrent(new Tenant([
        'domain' => 'zenith.localhost',
    ]));

    expect(config('app.url'))->toBe('http://zenith.localhost:8000')
        ->and(url('/api/collections'))->toBe('http://zenith.localhost:8000/api/collections');

    $task->forgetCurrent();

    expect(config('app.url'))->toBe('http://localhost:8000')
        ->and(url('/api/collections'))->toBe('http://localhost:8000/api/collections');
});
