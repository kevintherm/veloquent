<?php

use App\Models\RealtimeSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('prunes only expired realtime subscriptions', function () {
    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'collection_id' => (string) Str::ulid(),
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) Str::ulid(),
        'channel' => 'private-superusers.'.Str::ulid(),
        'filter' => '',
        'expired_at' => now()->subSecond(),
    ]);

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'collection_id' => (string) Str::ulid(),
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) Str::ulid(),
        'channel' => 'private-superusers.'.Str::ulid(),
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    expect(RealtimeSubscription::query()->count())->toBe(2);

    artisan('realtime:prune-expired-subscriptions')->assertSuccessful();

    expect(RealtimeSubscription::query()->count())->toBe(1);
    expect(RealtimeSubscription::query()->firstOrFail()->expired_at->isFuture())->toBeTrue();
});
