<?php

use App\Models\Superuser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated superuser can register a new superuser', function () {
    $actor = Superuser::factory()->create();

    $response = $this->actingAs($actor, 'api')->postJson('/api/superuser/register', [
        'name' => 'Super Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['access_token', 'token_type', 'expires_in', 'user'],
        ]);

    expect(Superuser::where('email', 'admin@example.com')->exists())->toBeTrue();
});

test('unauthenticated user cannot register a superuser', function () {
    $this->postJson('/api/superuser/register', [
        'name' => 'Super Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnauthorized();
});

test('authenticated superuser cannot register with duplicate email', function () {
    $actor = Superuser::factory()->create();
    Superuser::factory()->create(['email' => 'dupe@example.com']);

    $this->actingAs($actor, 'api')->postJson('/api/superuser/register', [
        'name' => 'Super Admin',
        'email' => 'dupe@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable();
});

test('superuser can login', function () {
    $superuser = Superuser::factory()->create();

    $response = $this->postJson('/api/superuser/login', [
        'email' => $superuser->email,
        'password' => 'password',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => ['access_token', 'token_type', 'expires_in', 'user'],
        ])
        ->assertJsonPath('data.user.is_superuser', true);
});

test('superuser cannot login with invalid credentials', function () {
    $superuser = Superuser::factory()->create();

    $this->postJson('/api/superuser/login', [
        'email' => $superuser->email,
        'password' => 'wrong-password',
    ])->assertUnauthorized();
});

test('superuser can get their own profile', function () {
    $superuser = Superuser::factory()->create();

    $this->actingAs($superuser, 'api')->getJson('/api/superuser/me')
        ->assertSuccessful()
        ->assertJsonFragment(['email' => $superuser->email])
        ->assertJsonPath('data.is_superuser', true);
});

test('unauthenticated user cannot access protected superuser routes', function () {
    $this->getJson('/api/superuser/me')->assertUnauthorized();
});

test('superuser can logout', function () {
    $superuser = Superuser::factory()->create();

    $this->actingAs($superuser, 'api')
        ->postJson('/api/superuser/logout')
        ->assertSuccessful()
        ->assertJsonFragment(['message' => 'Logged out successfully.']);
});
