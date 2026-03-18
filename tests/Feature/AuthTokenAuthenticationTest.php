<?php

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Models\AuthToken;
use App\Models\RealtimeSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('token_auth.max_active_tokens', 0);
});

function createAuthCollection(string $name): Collection
{
    return app(CreateCollectionAction::class)->execute([
        'name' => $name,
        'type' => CollectionType::Auth->value,
        'description' => ucfirst($name).' collection',
        'fields' => [
            ['name' => 'name', 'type' => 'text', 'nullable' => false, 'unique' => false],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
    ]);
}

function createAuthRecord(Collection $collection, string $email, string $password): Record
{
    return Record::of($collection)->create([
        'name' => 'Auth User',
        'email' => $email,
        'password' => $password,
    ]);
}

function bearerHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

it('returns opaque login payload keys and omits refresh fields', function () {
    $collection = createAuthCollection('auth_users_a');
    $user = createAuthRecord($collection, 'alice@example.test', 'password123');

    $response = postJson("/api/collections/{$collection->name}/auth/login", [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('data.collection_name', $collection->name);

    $data = $response->json('data');

    expect($data)->toHaveKeys(['token', 'expires_in', 'collection_name']);
    expect($data)->not->toHaveKeys(['access_token', 'refresh_token', 'refresh_token_expires_in']);
    expect($data['token'])->toBeString();
    expect(strlen($data['token']))->toBe(64);
    expect(substr_count($data['token'], '.'))->toBe(0);
    expect($data['expires_in'])->toBeInt();

    expect(AuthToken::query()->count())->toBe(1);
});

it('authenticates me endpoint with a valid opaque token', function () {
    $collection = createAuthCollection('auth_users_b');
    $user = createAuthRecord($collection, 'bob@example.test', 'password123');

    $login = postJson("/api/collections/{$collection->name}/auth/login", [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $token = $login->json('data.token');

    getJson("/api/collections/{$collection->name}/auth/me", bearerHeaders($token))
        ->assertSuccessful()
        ->assertJsonPath('data.email', $user->email);
});

it('rejects cross-collection me authentication with a token from another collection', function () {
    $collectionA = createAuthCollection('auth_users_c');
    $collectionB = createAuthCollection('auth_users_d');

    $userA = createAuthRecord($collectionA, 'carol@example.test', 'password123');
    createAuthRecord($collectionB, 'dave@example.test', 'password123');

    $loginA = postJson("/api/collections/{$collectionA->name}/auth/login", [
        'email' => $userA->email,
        'password' => 'password123',
    ]);

    $tokenA = $loginA->json('data.token');

    getJson("/api/collections/{$collectionB->name}/auth/me", bearerHeaders($tokenA))
        ->assertUnauthorized();
});

it('revokes all active tokens on logout-all', function () {
    $collection = createAuthCollection('auth_users_e');
    $user = createAuthRecord($collection, 'eve@example.test', 'password123');

    $firstLogin = postJson("/api/collections/{$collection->name}/auth/login", [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $secondLogin = postJson("/api/collections/{$collection->name}/auth/login", [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $tokenA = $firstLogin->json('data.token');
    $tokenB = $secondLogin->json('data.token');

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'collection_id' => $collection->id,
        'auth_collection' => $collection->name,
        'subscriber_id' => (string) $user->id,
        'channel' => 'private-'.$collection->name.'.'.$user->id,
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    deleteJson("/api/collections/{$collection->name}/auth/logout-all", [], bearerHeaders($tokenA))
        ->assertSuccessful();

    getJson("/api/collections/{$collection->name}/auth/me", bearerHeaders($tokenA))
        ->assertUnauthorized();

    getJson("/api/collections/{$collection->name}/auth/me", bearerHeaders($tokenB))
        ->assertUnauthorized();

    expect(AuthToken::query()->forRecord($collection->id, $user->id)->count())->toBe(0);
    expect(RealtimeSubscription::query()->count())->toBe(0);
});

it('rejects expired tokens', function () {
    $collection = createAuthCollection('auth_users_f');
    $user = createAuthRecord($collection, 'frank@example.test', 'password123');

    $tokenData = app(TokenAuthService::class)->generateToken($user);

    AuthToken::query()
        ->forRecord($collection->id, $user->id)
        ->update(['expires_at' => now()->subMinute()]);

    getJson("/api/collections/{$collection->name}/auth/me", bearerHeaders($tokenData['token']))
        ->assertUnauthorized();
});

it('returns not found for removed refresh endpoint', function () {
    $collection = createAuthCollection('auth_users_g');

    postJson("/api/collections/{$collection->name}/auth/refresh", [
        'refresh_token' => str_repeat('a', 64),
    ])->assertNotFound();
});

it('enforces max active tokens when set to one', function () {
    config()->set('token_auth.max_active_tokens', 1);

    $collection = createAuthCollection('auth_users_h');
    $user = createAuthRecord($collection, 'henry@example.test', 'password123');

    $firstLogin = postJson("/api/collections/{$collection->name}/auth/login", [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertSuccessful();

    $secondLogin = postJson("/api/collections/{$collection->name}/auth/login", [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertSuccessful();

    $firstToken = $firstLogin->json('data.token');
    $secondToken = $secondLogin->json('data.token');

    expect(AuthToken::query()->forRecord($collection->id, $user->id)->count())->toBe(1);

    getJson("/api/collections/{$collection->name}/auth/me", bearerHeaders($firstToken))
        ->assertUnauthorized();

    getJson("/api/collections/{$collection->name}/auth/me", bearerHeaders($secondToken))
        ->assertSuccessful();
});
