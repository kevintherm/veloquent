<?php

namespace Tests\Feature;

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\OAuth\Models\OAuthProvider;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superuserCollection = Collection::where('name', 'superusers')->first() ?? Collection::factory()->create([
        'name' => 'superusers',
        'type' => CollectionType::Auth,
    ]);

    $this->user = Record::of($this->superuserCollection)->where('email', 'admin@example.com')->first()
        ?? Record::of($this->superuserCollection)->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

    $tokenData = app(TokenAuthService::class)->generateToken($this->user);
    $this->withToken($tokenData['token']);

    $this->collection = Collection::where('name', 'test_collection')->first() ?? Collection::factory()->auth()->create([
        'name' => 'test_collection',
        'fields' => [
            ['name' => 'email', 'type' => 'email', 'required' => true],
        ],
    ]);
});

it('lists oauth providers for a collection', function () {
    OAuthProvider::factory()->create([
        'collection_id' => $this->collection->id,
        'provider' => 'google',
    ]);

    $response = $this->getJson("/api/collections/{$this->collection->id}/oauth-providers");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.provider', 'google');
});

it('creates an oauth provider configuration', function () {
    $response = $this->postJson("/api/collections/{$this->collection->id}/oauth-providers", [
        'provider' => 'github',
        'client_id' => 'gh-id',
        'client_secret' => 'gh-secret',
        'enabled' => true,
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('oauth_providers', [
        'collection_id' => $this->collection->id,
        'provider' => 'github',
        'client_id' => 'gh-id',
    ]);
});

it('updates an oauth provider configuration', function () {
    $p = OAuthProvider::factory()->create([
        'collection_id' => $this->collection->id,
        'provider' => 'facebook',
        'client_id' => 'old-id',
    ]);

    $response = $this->putJson("/api/collections/{$this->collection->id}/oauth-providers/{$p->id}", [
        'provider' => 'facebook',
        'client_id' => 'new-id',
        'client_secret' => 'new-secret',
        'enabled' => false,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('oauth_providers', [
        'id' => $p->id,
        'client_id' => 'new-id',
        'enabled' => false,
    ]);
});

it('deletes an oauth provider configuration', function () {
    $p = OAuthProvider::factory()->create([
        'collection_id' => $this->collection->id,
        'provider' => 'discord',
    ]);

    $response = $this->deleteJson("/api/collections/{$this->collection->id}/oauth-providers/{$p->id}");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('oauth_providers', ['id' => $p->id]);
});

it('denies access to non-superusers', function () {
    $record = Record::of($this->collection);
    $record->fill(['email' => 'denied@example.com']);
    $record->save();

    $tokenData = app(TokenAuthService::class)->generateToken($record);
    $this->withToken($tokenData['token']);

    $this->getJson("/api/collections/{$this->collection->id}/oauth-providers")
        ->assertStatus(403);
});
