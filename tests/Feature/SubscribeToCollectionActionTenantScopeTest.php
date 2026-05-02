<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Actions\SubscribeToCollectionAction;
use App\Domain\Realtime\Actions\UnsubscribeFromCollectionAction;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Realtime\Models\RealtimeSubscription;
use App\Domain\Records\Models\Record;
use App\Infrastructure\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $tenant = new Tenant;
    $tenant->forceFill(['id' => 1001, 'database' => ':memory:']);
    $landlordConnection = (string) config('multitenancy.landlord_database_connection_name', 'landlord');

    app()->instance((string) config('multitenancy.current_tenant_container_key'), $tenant);

    if (! Schema::connection($landlordConnection)->hasTable('realtime_subscriptions')) {
        Schema::connection($landlordConnection)->create('realtime_subscriptions', function (Blueprint $table): void {
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

afterEach(function (): void {
    app()->forgetInstance((string) config('multitenancy.current_tenant_container_key'));
});

function fakeRealtimeBus(array &$payloads): RealtimeBusDriver
{
    return new class($payloads) implements RealtimeBusDriver
    {
        /**
         * @param  array<int, array<string, mixed>>  $payloads
         */
        public function __construct(private array &$payloads) {}

        public function publish(array $payload): void
        {
            $this->payloads[] = $payload;
        }

        public function listen(callable $callback, Closure $shouldStop): void
        {
            // Not needed in action tests.
        }
    };
}

function fakeCollection(string $name): Collection
{
    $collection = new Collection([
        'name' => $name,
        'type' => CollectionType::Base,
        'table_name' => '_velo_'.$name,
        'fields' => [],
        'api_rules' => [],
    ]);

    $collection->forceFill([
        'id' => (string) Str::ulid(),
    ]);

    return $collection;
}

function fakeUser(Collection $authCollection): Record
{
    $record = Record::of($authCollection);
    $record->forceFill([
        'id' => (string) Str::ulid(),
    ]);

    return $record;
}

it('stores subscriptions in landlord table with tenant_id and publishes tenant-aware subscribe event', function () {
    $publishedPayloads = [];

    $authCollection = fakeCollection('superusers');
    $targetCollection = fakeCollection('posts');
    $user = fakeUser($authCollection);

    $action = new SubscribeToCollectionAction(fakeRealtimeBus($publishedPayloads));

    $result = $action->execute($user, $targetCollection, 'name = "Hello"');

    $subscription = RealtimeSubscription::query()->firstOrFail();

    expect($result)->toHaveKeys(['channel', 'expires_at']);
    expect($subscription->tenant_id)->toBe(1001)
        ->and($subscription->collection_id)->toBe($targetCollection->id)
        ->and($subscription->auth_collection)->toBe('superusers')
        ->and($subscription->subscriber_id)->toBe((string) $user->getKey())
        ->and($subscription->filter)->toBe('name = "Hello"');

    expect($publishedPayloads)->toHaveCount(1);
    expect($publishedPayloads[0])->toMatchArray([
        'type' => 'connection',
        'action' => 'subscribe',
        'tenant_id' => 1001,
        'collection_id' => $targetCollection->id,
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) $user->getKey(),
    ]);
});

it('deletes subscriptions by tenant_id and publishes tenant-aware unsubscribe event', function () {
    $publishedPayloads = [];

    $authCollection = fakeCollection('superusers');
    $targetCollection = fakeCollection('posts');
    $user = fakeUser($authCollection);

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'tenant_id' => 1001,
        'collection_id' => $targetCollection->id,
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) $user->getKey(),
        'channel' => 'private-superusers.'.$user->getKey(),
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'tenant_id' => 2002,
        'collection_id' => $targetCollection->id,
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) $user->getKey(),
        'channel' => 'private-superusers.'.$user->getKey(),
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    $action = new UnsubscribeFromCollectionAction(fakeRealtimeBus($publishedPayloads));
    $action->execute($user, $targetCollection);

    expect(RealtimeSubscription::query()->where('tenant_id', 1001)->count())->toBe(0)
        ->and(RealtimeSubscription::query()->where('tenant_id', 2002)->count())->toBe(1);

    expect($publishedPayloads)->toHaveCount(1);
    expect($publishedPayloads[0])->toMatchArray([
        'type' => 'connection',
        'action' => 'unsubscribe',
        'tenant_id' => 1001,
        'collection_id' => $targetCollection->id,
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) $user->getKey(),
    ]);
});
