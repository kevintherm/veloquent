<?php

use Veloquent\Core\Domain\Collections\Actions\CreateCollectionAction;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Auth\Models\AuthToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->collection = app(CreateCollectionAction::class)->execute([
        'type' => 'auth',
        'name' => 'login_test_users',
        'fields' => [],
        'indexes' => [],
    ]);

    $this->user = Record::of($this->collection)->create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);
});

it('excludes expired and revoked tokens from active scope', function () {
    // 1. Active token
    $active = AuthToken::create([
        'collection_name' => $this->collection->name,
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', 'active'),
        'expires_at' => now()->addHour(),
    ]);

    // 2. Expired token
    $expired = AuthToken::create([
        'collection_name' => $this->collection->name,
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', 'expired'),
        'expires_at' => now()->subHour(),
    ]);

    // 3. Revoked token
    $revoked = AuthToken::create([
        'collection_name' => $this->collection->name,
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', 'revoked'),
        'expires_at' => now()->addHour(),
        'revoked_at' => now(),
    ]);

    $activeTokens = AuthToken::query()->active()->get();

    expect($activeTokens->count())->toBe(1);
    expect($activeTokens->first()->id)->toBe($active->id);
});

it('prunes only expired or revoked tokens via artisan command', function () {
    // 1. Active token
    $active = AuthToken::create([
        'collection_name' => $this->collection->name,
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', 'active'),
        'expires_at' => now()->addHour(),
    ]);

    // 2. Expired token
    $expired = AuthToken::create([
        'collection_name' => $this->collection->name,
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', 'expired'),
        'expires_at' => now()->subHour(),
    ]);

    // 3. Revoked token
    $revoked = AuthToken::create([
        'collection_name' => $this->collection->name,
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', 'revoked'),
        'expires_at' => now()->addHour(),
        'revoked_at' => now(),
    ]);

    $this->artisan('auth:prune-tokens')
        ->expectsOutput('[auth:prune-tokens] Deleted 2 expired or revoked auth token(s).')
        ->assertSuccessful();

    $this->assertDatabaseHas('auth_tokens', ['id' => $active->id]);
    $this->assertDatabaseMissing('auth_tokens', ['id' => $expired->id]);
    $this->assertDatabaseMissing('auth_tokens', ['id' => $revoked->id]);
});
