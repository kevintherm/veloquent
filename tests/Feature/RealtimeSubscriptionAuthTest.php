<?php

use Veloquent\Core\Domain\Collections\Actions\CreateCollectionAction;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Realtime\Models\RealtimeSubscription;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Support\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $tenant = app('currentTenant');
    
    if (! Schema::connection('testbench')->hasTable('realtime_subscriptions')) {
        Schema::connection('testbench')->create('realtime_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->ulid('collection_id');
            $table->string('auth_collection');
            $table->ulid('subscriber_id');
            $table->string('channel');
            $table->text('filter')->nullable();
            $table->timestamp('expired_at');
            $table->timestamps();

            $table->index('tenant_id', 'rt_subs_tenant_idx');
            $table->index('collection_id', 'rt_subs_collection_idx');
            $table->index('expired_at', 'rt_subs_expired_at_idx');
            $table->index(['tenant_id', 'collection_id'], 'rt_subs_tenant_collection_idx');
            $table->index(['tenant_id', 'expired_at'], 'rt_subs_tenant_expired_idx');
            $table->index(['collection_id', 'expired_at'], 'rt_subs_collection_expired_idx');
            $table->unique(
                ['tenant_id', 'collection_id', 'auth_collection', 'subscriber_id'],
                'rt_subs_tenant_collection_auth_sub_uq'
            );
        });
    }
});

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
            ...($type === CollectionType::Auth ? ['manage' => ''] : []),
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

    $this->withToken($token)->postJson(
        "/api/collections/{$targetCollection->name}/subscribe",
        ['filter' => 'name = "Hello"']
    )->assertSuccessful();

    expect(RealtimeSubscription::query()->count())->toBe(1);
    expect(RealtimeSubscription::query()->first()?->tenant_id)->toBe(app('currentTenant')->id);
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
        'tenant_id' => app('currentTenant')->id,
        'collection_id' => $firstTarget->id,
        'auth_collection' => $authCollection->name,
        'subscriber_id' => (string) $user->id,
        'channel' => 'private-'.$authCollection->name.'.'.$user->id,
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'tenant_id' => app('currentTenant')->id,
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
