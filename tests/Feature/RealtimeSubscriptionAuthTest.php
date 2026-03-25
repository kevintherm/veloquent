<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Models\RealtimeSubscription;
use App\Domain\Records\Models\Record;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function createRealtimeCollection(string $name, CollectionType $type): Collection
{
    return app(CreateCollectionAction::class)->execute([
        'name' => $name,
        'type' => $type->value,
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

function createRealtimeAuthRecordFor(Collection $collection, string $email, string $password): Record
{
    return Record::of($collection)->create([
        'name' => 'Realtime User',
        'email' => $email,
        'password' => $password,
    ]);
}

function loginRealtimeToken(Collection $authCollection, string $email, string $password): string
{
    $response = postJson("/api/collections/{$authCollection->name}/auth/login", [
        'identity' => $email,
        'password' => $password,
    ])->assertSuccessful();

    return (string) $response->json('data.token');
}

function realtimeBearerHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

it('rejects guest subscriptions', function () {
    $targetCollection = createRealtimeCollection('realtime_target_guest', CollectionType::Base);

    postJson("/api/collections/{$targetCollection->name}/subscribe")
        ->assertUnauthorized();
});

it('creates and removes subscriptions with opaque token auth', function () {
    $authCollection = createRealtimeCollection('realtime_auth_users', CollectionType::Auth);
    $targetCollection = createRealtimeCollection('realtime_target_records', CollectionType::Base);
    $user = createRealtimeAuthRecordFor($authCollection, 'realtime@example.test', 'password123');

    $token = loginRealtimeToken($authCollection, $user->email, 'password123');

    postJson(
        "/api/collections/{$targetCollection->name}/subscribe",
        ['filter' => 'name = "Hello"'],
        realtimeBearerHeaders($token)
    )
        ->assertSuccessful();

    expect(RealtimeSubscription::query()->count())->toBe(1);
    expect(RealtimeSubscription::query()->first()?->expired_at)->not->toBeNull();

    deleteJson(
        "/api/collections/{$targetCollection->name}/subscribe",
        [],
        realtimeBearerHeaders($token)
    )
        ->assertSuccessful();

    expect(RealtimeSubscription::query()->count())->toBe(0);
});

it('slides subscription expiry when subscribe is called again', function () {
    Carbon::setTestNow(now());

    $authCollection = createRealtimeCollection('realtime_auth_sliding', CollectionType::Auth);
    $targetCollection = createRealtimeCollection('realtime_target_sliding', CollectionType::Base);
    $user = createRealtimeAuthRecordFor($authCollection, 'sliding@example.test', 'password123');

    $token = loginRealtimeToken($authCollection, $user->email, 'password123');

    postJson(
        "/api/collections/{$targetCollection->name}/subscribe",
        ['filter' => ''],
        realtimeBearerHeaders($token)
    )->assertSuccessful();

    $firstExpiration = RealtimeSubscription::query()->firstOrFail()->expired_at;

    Carbon::setTestNow(now()->addSeconds(10));

    postJson(
        "/api/collections/{$targetCollection->name}/subscribe",
        ['filter' => ''],
        realtimeBearerHeaders($token)
    )->assertSuccessful();

    $secondExpiration = RealtimeSubscription::query()->firstOrFail()->expired_at;

    expect($secondExpiration?->gt($firstExpiration))->toBeTrue();

    Carbon::setTestNow();
});

it('removes all realtime subscriptions on logout-all', function () {
    $authCollection = createRealtimeCollection('realtime_auth_logout', CollectionType::Auth);
    $firstTarget = createRealtimeCollection('realtime_target_first', CollectionType::Base);
    $secondTarget = createRealtimeCollection('realtime_target_second', CollectionType::Base);
    $user = createRealtimeAuthRecordFor($authCollection, 'logout-realtime@example.test', 'password123');

    $token = loginRealtimeToken($authCollection, $user->email, 'password123');

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'collection_id' => $firstTarget->id,
        'auth_collection' => $authCollection->name,
        'subscriber_id' => (string) $user->id,
        'channel' => 'private-'.$authCollection->name.'.'.$user->id,
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'collection_id' => $secondTarget->id,
        'auth_collection' => $user->getTable(),
        'subscriber_id' => (string) $user->id,
        'channel' => 'private-'.$user->getTable().'.'.$user->id,
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    expect(RealtimeSubscription::query()->count())->toBe(2);

    deleteJson(
        "/api/collections/{$authCollection->name}/auth/logout-all",
        [],
        realtimeBearerHeaders($token)
    )->assertSuccessful();

    expect(RealtimeSubscription::query()->count())->toBe(0);
});
