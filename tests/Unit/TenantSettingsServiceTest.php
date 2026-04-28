<?php

use App\Domain\Settings\EmailSettings;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\StorageSettings;
use App\Domain\Settings\TenantSettingsService;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class);

it('caches settings and reads from cache', function () {
    $service = app(TenantSettingsService::class);

    $cachedData = [
        'general' => ['app_name' => 'Test App'],
        'storage' => [],
        'email' => [],
    ];

    Cache::shouldReceive('rememberForever')
        ->once()
        ->with('tenant_api_settings', Closure::class)
        ->andReturn($cachedData);

    $result = $service->get();

    expect($result)->toBe($cachedData);
});

it('clears cache on update', function () {
    $general = $this->mock(GeneralSettings::class, function (MockInterface $mock) {
        $mock->shouldReceive('save')->once();
    });
    $storage = $this->mock(StorageSettings::class, function (MockInterface $mock) {
        $mock->shouldReceive('save')->never();
    });
    $email = $this->mock(EmailSettings::class, function (MockInterface $mock) {
        $mock->shouldReceive('save')->never();
    });

    $service = new TenantSettingsService($general, $storage, $email);

    Cache::shouldReceive('forget')
        ->once()
        ->with('tenant_api_settings');

    $service->update(['general' => ['app_name' => 'Updated App']]);
});
