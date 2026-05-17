<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Auth\Models\AuthToken;
use Veloquent\Core\Support\Models\Tenant;
use function Pest\Laravel\{postJson, getJson, deleteJson};

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createAuthCollection(string $name): Collection
{
    return Collection::create([
        'name' => $name,
        'type' => 'auth',
        'fields' => [
            ['name' => 'email', 'type' => 'email', 'unique' => true],
            ['name' => 'password', 'type' => 'text'],
        ],
        'options' => [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'identity_fields' => ['email'],
                ],
            ],
        ],
    ]);
}

function createAuthRecord(Collection $collection, string $email, string $password): Record
{
    return Record::of($collection)->create([
        'email' => $email,
        'password' => $password,
    ]);
}

function bearerHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
    ];
}

beforeEach(function () {
    config()->set('velo.auth.token.max_active_tokens', 0);

    $tenant = Tenant::query()->updateOrCreate(['domain' => 'localhost'], [
        'id' => 1001,
        'name' => 'test-tenant',
        'database' => ':memory:',
    ]);

    $tenant->makeCurrent();

    $landlordConnection = (string) config('multitenancy.landlord_database_connection_name', 'landlord');

    if (! Schema::hasTable('schema_jobs')) {
        Schema::create('schema_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('collection_id');
            $table->string('status');
            $table->json('payload');
            $table->timestamps();
        });
    }

    if (! Schema::connection($landlordConnection)->hasTable('realtime_subscriptions')) {
        Schema::connection($landlordConnection)->create('realtime_subscriptions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('collection_id');
            $table->string('record_id')->nullable();
            $table->string('event');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }
});

it('returns opaque login payload keys and omits refresh fields', function () {
    $collection = createAuthCollection('auth_users_a');
    $user = createAuthRecord($collection, 'test@example.test', 'password123');

    postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'token',
                'expires_in',
                'collection_name',
            ],
        ])
        ->assertJsonMissingPath('data.refresh_token')
        ->assertJsonMissingPath('data.refresh_expires_in');
});

it('authenticates me endpoint with a valid opaque token', function () {
    $collection = createAuthCollection('auth_users_b');
    $user = createAuthRecord($collection, 'bob@example.test', 'password123');

    $login = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $token = $login->json('data.token');

    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful()
        ->assertJsonPath('data.email', $user->email);
});

it('rejects cross-collection me authentication with a token from another collection', function () {
    $collectionA = createAuthCollection('auth_users_c1');
    $collectionB = createAuthCollection('auth_users_c2');

    $userA = createAuthRecord($collectionA, 'user-a@example.test', 'password123');
    $userB = createAuthRecord($collectionB, 'user-b@example.test', 'password123');

    $loginA = postJson("/api/collections/{$collectionA->name}/auth/login", [
        'identity' => $userA->email,
        'password' => 'password123',
    ]);

    $tokenA = $loginA->json('data.token');

    // Try to authenticate with token A on collection B's me endpoint
    $this->withToken($tokenA)
        ->getJson("/api/collections/{$collectionB->name}/auth/me")
        ->assertUnauthorized();
});

it('revokes all active tokens on logout-all', function () {
    $collection = createAuthCollection('auth_users_d');
    $user = createAuthRecord($collection, 'logout@example.test', 'password123');

    $loginA = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $tokenA = $loginA->json('data.token');

    $loginB = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $tokenB = $loginB->json('data.token');

    AuthToken::query()
        ->forRecord($collection->id, $user->id)
        ->update(['expires_at' => now()->addMinute()]);

    $this->withToken($tokenA)
        ->deleteJson("/api/collections/{$collection->name}/auth/logout-all")
        ->assertSuccessful();

    Auth::guard('api')->forgetUser();

    $this->withToken($tokenA)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();

    $this->withToken($tokenB)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();
});

it('rejects expired tokens', function () {
    $collection = createAuthCollection('auth_users_f');
    $user = createAuthRecord($collection, 'expired@example.test', 'password123');

    $login = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $tokenData = (object) $login->json('data');

    AuthToken::query()
        ->forRecord($collection->id, $user->id)
        ->update(['expires_at' => now()->subMinute()]);

    $this->withToken($tokenData->token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();
});

it('returns not found for removed refresh endpoint', function () {
    $collection = createAuthCollection('auth_users_g');

    postJson("/api/collections/{$collection->name}/auth/refresh")
        ->assertNotFound();
});

it('enforces max active tokens when set to one', function () {
    config()->set('velo.auth.token.max_active_tokens', 1);

    $collection = createAuthCollection('auth_users_h');
    $user = createAuthRecord($collection, 'max@example.test', 'password123');

    $firstToken = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ])->json('data.token');

    $secondToken = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ])->json('data.token');

    Auth::guard('api')->forgetUser();

    $this->withToken($firstToken)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();

    $this->withToken($secondToken)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful();
});
