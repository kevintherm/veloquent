<?php

use Veloquent\Core\Domain\Auth\Models\Superuser;
use Veloquent\Core\Domain\Settings\GeneralSettings;
use Veloquent\Core\Domain\Settings\Resolvers\TenantStorageResolver;
use Veloquent\Core\Support\Http\Middleware\TokenAuthMiddleware;
use Veloquent\Core\Support\Models\Tenant;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function () {
    $this->tenant = Tenant::current();

    $this->user = Superuser::factory()->create();

    $this->mock(TenantStorageResolver::class, function ($mock) {
        $mock->shouldReceive('testConnection')->andReturn(true);
    })->makePartial();

    Mail::fake();

    withoutMiddleware(TokenAuthMiddleware::class);
});

it('can get settings', function () {
    actingAs($this->user, 'api');

    $response = getJson('/api/settings');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'general' => [
                'app_name',
                'app_url',
                'locale',
                'contact_email',
                'lock_schema_change',
            ],
            'storage' => [
                'storage_driver',
                's3_key',
                's3_secret',
                's3_region',
                's3_bucket',
                's3_endpoint',
            ],
            'email' => [
                'mail_driver',
                'mail_host',
                'mail_port',
                'mail_encryption',
                'mail_username',
                'mail_password',
                'mail_from_address',
                'mail_from_name',
            ],
        ],
    ]);
});

it('can validate settings payload', function () {
    actingAs($this->user, 'api');

    // Sending empty payload is fine now because everything is independent and optional
    $response = patchJson('/api/settings', []);
    $response->assertStatus(200);

    // Invalid parameters when a section is provided should trigger validation errors
    $invalidResponse = patchJson('/api/settings', [
        'general' => ['app_name' => ''],
        'storage' => ['storage_driver' => 'invalid-driver'],
        'email' => ['mail_driver' => 'invalid-driver'],
        'ai' => ['ai_provider' => 'invalid-provider'],
    ]);
    $invalidResponse->assertStatus(422);
    $invalidResponse->assertJsonValidationErrors([
        'general.app_name',
        'storage.storage_driver',
        'email.mail_driver',
        'ai.ai_provider',
    ]);
});

it('can update settings', function () {
    actingAs($this->user, 'api');

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::type(\Laravel\Ai\Prompts\AgentPrompt::class))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'OK',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    \Laravel\Ai\Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    $response = patchJson('/api/settings', [
        'general' => [
            'app_name' => 'Custom App Name',
            'app_url' => 'https://custom.example.com',
            'locale' => 'en',
            'contact_email' => 'admin@test.com',
            'lock_schema_change' => true,
        ],
        'storage' => [
            'storage_driver' => 's3',
            's3_key' => 'test-key',
            's3_secret' => 'test-secret',
            's3_region' => 'us-east-1',
            's3_bucket' => 'test-bucket',
            's3_endpoint' => 'https://s3.amazonaws.com',
        ],
        'email' => [
            'mail_driver' => 'mailgun',
            'mail_host' => 'smtp.mailgun.org',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'user',
            'mail_password' => 'secret-mail-pass',
            'mail_from_address' => 'hello@test.com',
            'mail_from_name' => 'Support',
        ],
        'ai' => [
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'ai_api_key' => 'secret-api-key',
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.general.app_name', 'Custom App Name');

    // Check if it's saved in the object
    expect(app(GeneralSettings::class)->app_name)->toBe('Custom App Name');
});

it('denies non-superusers from managing settings', function () {
    // In this app, superusers are Superuser model and regular users don't have access.
    // Let's create a regular API request without superuser authentication.
    $response = getJson('/api/settings');
    $response->assertStatus(401);

    $response = patchJson('/api/settings', []);
    $response->assertStatus(401);
});

it('supports both encrypted and unencrypted settings caching', function () {
    // 1. Verify encrypted cache (default)
    config(['settings.cache.enabled' => true]);
    config(['settings.cache.encrypted' => true]);
    GeneralSettings::clearCache();
    
    $settings = app(GeneralSettings::class);
    $settings->app_name = 'Encrypted App';
    $settings->save();
    
    // Clear instance and reload to hit DB and warm cache
    app()->forgetInstance(GeneralSettings::class);
    $loaded = app(GeneralSettings::class);
    $loaded->app_name; // trigger load
    
    // Check cache is encrypted string
    $cacheKey = 'settings.general';
    expect(Illuminate\Support\Facades\Cache::get($cacheKey))->toBeString();
    
    // 2. Verify unencrypted cache
    config(['settings.cache.encrypted' => false]);
    GeneralSettings::clearCache();
    
    // Clear instance and save again under unencrypted config
    app()->forgetInstance(GeneralSettings::class);
    $settingsUnencrypted = app(GeneralSettings::class);
    $settingsUnencrypted->app_name = 'Unencrypted App';
    $settingsUnencrypted->save();
    
    // Clear instance and reload to hit DB and warm cache
    app()->forgetInstance(GeneralSettings::class);
    $loadedUnencrypted = app(GeneralSettings::class);
    $loadedUnencrypted->app_name; // trigger load
    
    // Check cache is raw array
    expect(Illuminate\Support\Facades\Cache::get($cacheKey))->toBeArray();
});
