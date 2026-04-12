<?php

use App\Domain\Realtime\Models\RealtimeSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $landlordConnection = (string) config('multitenancy.landlord_database_connection_name', 'landlord');

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

it('prunes only expired realtime subscriptions', function () {
    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'tenant_id' => 1001,
        'collection_id' => (string) Str::ulid(),
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) Str::ulid(),
        'channel' => 'private-superusers.'.Str::ulid(),
        'filter' => '',
        'expired_at' => now()->subSecond(),
    ]);

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'tenant_id' => 1002,
        'collection_id' => (string) Str::ulid(),
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) Str::ulid(),
        'channel' => 'private-superusers.'.Str::ulid(),
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    expect(RealtimeSubscription::query()->count())->toBe(2);

    artisan('realtime:prune-expired')->assertSuccessful();

    expect(RealtimeSubscription::query()->count())->toBe(1);
    expect(RealtimeSubscription::query()->firstOrFail()->expired_at->isFuture())->toBeTrue();
});
