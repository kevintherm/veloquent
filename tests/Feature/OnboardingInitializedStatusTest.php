<?php

use App\Models\Superuser;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('returns false when no superuser exists yet', function () {
    postJson('/api/onboarding/initialized')
        ->assertSuccessful()
        ->assertJsonPath('data', false);
});

it('returns true after the first superuser is created', function () {
    $superuser = new Superuser;
    $superuser->forceFill([
        'name' => 'First Admin',
        'email' => 'first-admin@example.test',
        'password' => 'password123',
    ]);
    $superuser->save();

    postJson('/api/onboarding/initialized')
        ->assertSuccessful()
        ->assertJsonPath('data', true);
});
