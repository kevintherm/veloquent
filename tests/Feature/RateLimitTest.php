<?php

use Veloquent\Core\Domain\Auth\Models\Superuser;
use Veloquent\Core\Domain\Settings\RateLimitSettings;
use Veloquent\Core\Support\Models\Tenant;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::current();
    $this->user = Superuser::factory()->create();

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $this->regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $this->regularUser->setAttribute('id', 42);

    // Reset rate limit settings
    $settings = app(RateLimitSettings::class);
    $settings->rate_limit_enabled = true;
    $settings->rate_limit_rules = [];
    $settings->save();
    app()->forgetInstance(RateLimitSettings::class);

    // Mock Gate authorization to bypass 403s on collection controller
    Gate::shouldReceive('authorize')->andReturnNull();

    // Clear rate limiter cache
    RateLimiter::clear('api');
    RateLimiter::clear('auth');
    RateLimiter::clear('otp');
});

it('bypasses rate limits when rate limiting is globally disabled', function () {
    $settings = app(RateLimitSettings::class);
    $settings->rate_limit_enabled = false;
    $settings->rate_limit_rules = [
        [
            'label' => '*',
            'max_attempts' => 1,
            'decay_minutes' => 1,
            'audience' => 'all',
        ]
    ];
    $settings->save();
    app()->forgetInstance(RateLimitSettings::class);

    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(200);
});

it('applies general rate limit rule correctly', function () {
    $settings = app(RateLimitSettings::class);
    $settings->rate_limit_rules = [
        [
            'label' => '*',
            'max_attempts' => 2,
            'decay_minutes' => 1,
            'audience' => 'all',
        ]
    ];
    $settings->save();
    app()->forgetInstance(RateLimitSettings::class);

    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(429);
});

it('applies path wildcard matching rule correctly', function () {
    $settings = app(RateLimitSettings::class);
    $settings->rate_limit_rules = [
        [
            'label' => '/api/collections*',
            'max_attempts' => 1,
            'decay_minutes' => 1,
            'audience' => 'all',
        ]
    ];
    $settings->save();
    app()->forgetInstance(RateLimitSettings::class);

    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(429);
});

it('applies tag *:create matching rule correctly', function () {
    $settings = app(RateLimitSettings::class);
    $settings->rate_limit_rules = [
        [
            'label' => '*:create',
            'max_attempts' => 1,
            'decay_minutes' => 1,
            'audience' => 'all',
        ]
    ];
    $settings->save();
    app()->forgetInstance(RateLimitSettings::class);

    postJson('/api/collections', [])->assertStatus(422);
    postJson('/api/collections', [])->assertStatus(429);
});

it('applies guest audience filtering correctly', function () {
    $settings = app(RateLimitSettings::class);
    $settings->rate_limit_rules = [
        [
            'label' => '*',
            'max_attempts' => 1,
            'decay_minutes' => 1,
            'audience' => 'guest',
        ]
    ];
    $settings->save();
    app()->forgetInstance(RateLimitSettings::class);

    // As a guest:
    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(429);

    // Clear limiter cache for test isolation
    RateLimiter::clear('api');

    // As authenticated user:
    actingAs($this->regularUser, 'api');
    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(200);
});

it('applies auth audience filtering correctly', function () {
    $settings = app(RateLimitSettings::class);
    $settings->rate_limit_rules = [
        [
            'label' => '*',
            'max_attempts' => 1,
            'decay_minutes' => 1,
            'audience' => 'auth',
        ]
    ];
    $settings->save();
    app()->forgetInstance(RateLimitSettings::class);

    // As a guest:
    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(200);

    // As authenticated user:
    actingAs($this->regularUser, 'api');
    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(429);
});

it('bypasses rate limits for superusers', function () {
    $settings = app(RateLimitSettings::class);
    $settings->rate_limit_rules = [
        [
            'label' => '*',
            'max_attempts' => 1,
            'decay_minutes' => 1,
            'audience' => 'all',
        ]
    ];
    $settings->save();
    app()->forgetInstance(RateLimitSettings::class);

    // As superuser:
    actingAs($this->user, 'api');
    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(200);
    getJson('/api/collections')->assertStatus(200);
});
