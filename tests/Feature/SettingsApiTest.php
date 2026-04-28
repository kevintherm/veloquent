<?php

use App\Domain\Auth\Models\Superuser;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\Resolvers\TenantStorageResolver;
use App\Http\Middleware\TokenAuthMiddleware;
use App\Infrastructure\Models\Tenant;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Test Tenant',
        'domain' => 'test.localhost',
        'database' => 'test_tenant_db',
    ]);
    $this->tenant->makeCurrent();

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
                'timezone',
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

    $response = patchJson('/api/settings', []);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['general', 'storage', 'email']);
});

it('can update settings', function () {
    actingAs($this->user, 'api');

    $response = patchJson('/api/settings', [
        'general' => [
            'app_name' => 'Custom App Name',
            'app_url' => 'https://custom.example.com',
            'timezone' => 'America/New_York',
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
