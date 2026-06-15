<?php

use Veloquent\Core\Domain\Settings\EmailSettings;
use Veloquent\Core\Domain\Settings\GeneralSettings;
use Veloquent\Core\Domain\Settings\StorageSettings;
use Veloquent\Core\Domain\Settings\AiSettings;
use Veloquent\Core\Domain\Settings\RateLimitSettings;
use Veloquent\Core\Domain\Settings\TenantSettingsService;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Veloquent\Core\Tests\TestCase;

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
    $ai = $this->mock(AiSettings::class, function (MockInterface $mock) {
        $mock->shouldReceive('save')->never();
    });
    $rateLimit = $this->mock(RateLimitSettings::class, function (MockInterface $mock) {
        $mock->shouldReceive('save')->never();
    });

    $service = new TenantSettingsService($general, $storage, $email, $ai, $rateLimit);

    Cache::shouldReceive('forget')
        ->once()
        ->with('tenant_api_settings');

    $service->update(['general' => ['app_name' => 'Updated App']]);
});

it('resolves dynamic defaults from config in EmailSettings', function () {
    EmailSettings::clearCache();

    config([
        'mail.default' => 'custom_smtp',
        'mail.mailers.smtp.host' => 'custom-smtp-host.test',
        'mail.mailers.smtp.port' => 587,
        'mail.mailers.smtp.encryption' => 'ssl',
        'mail.mailers.smtp.username' => 'custom_user',
        'mail.mailers.smtp.password' => 'custom_pass',
        'mail.from.address' => 'custom-sender@example.com',
        'mail.from.name' => 'Custom Sender Name',
    ]);

    DB::table('settings')->truncate();

    $emailSettings = new EmailSettings();


    expect($emailSettings->mail_driver)->toBe('custom_smtp');
    expect($emailSettings->mail_host)->toBe('custom-smtp-host.test');
    expect($emailSettings->mail_port)->toBe(587);
    expect($emailSettings->mail_encryption)->toBe('ssl');
    expect($emailSettings->mail_username)->toBe('custom_user');
    expect($emailSettings->mail_password)->toBe('custom_pass');
    expect($emailSettings->mail_from_address)->toBe('custom-sender@example.com');
    expect($emailSettings->mail_from_name)->toBe('Custom Sender Name');
});

