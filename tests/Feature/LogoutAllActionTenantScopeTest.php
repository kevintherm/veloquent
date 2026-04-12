<?php

use App\Domain\Auth\Actions\LogoutAllAction;
use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
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
    $tenant->forceFill(['id' => 1001]);
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

it('deletes realtime subscriptions only for current tenant on logout-all', function () {
    $publishedPayloads = [];

    $tokenService = new class extends TokenAuthService
    {
        /** @var list<array{collection_id: string, record_id: string}> */
        public array $calls = [];

        public function revokeRecordTokens(string $collectionId, string $recordId, ?string $tokenHash = null): int
        {
            $this->calls[] = [
                'collection_id' => $collectionId,
                'record_id' => $recordId,
            ];

            return 1;
        }
    };

    $bus = new class($publishedPayloads) implements RealtimeBusDriver
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
            // Not needed in this action test.
        }
    };

    $collection = new Collection([
        'name' => 'superusers',
        'type' => CollectionType::Auth,
        'table_name' => 'superusers',
        'fields' => [],
        'api_rules' => [],
    ]);

    $collection->forceFill([
        'id' => (string) Str::ulid(),
    ]);

    $user = Record::of($collection);
    $user->forceFill(['id' => (string) Str::ulid()]);

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'tenant_id' => 1001,
        'collection_id' => (string) Str::ulid(),
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) $user->getKey(),
        'channel' => 'private-superusers.'.$user->getKey(),
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    RealtimeSubscription::query()->create([
        'id' => (string) Str::ulid(),
        'tenant_id' => 2002,
        'collection_id' => (string) Str::ulid(),
        'auth_collection' => 'superusers',
        'subscriber_id' => (string) $user->getKey(),
        'channel' => 'private-superusers.'.$user->getKey(),
        'filter' => '',
        'expired_at' => now()->addMinute(),
    ]);

    $action = new LogoutAllAction($tokenService, $bus);
    $action->execute($user);

    expect(RealtimeSubscription::query()->where('tenant_id', 1001)->count())->toBe(0)
        ->and(RealtimeSubscription::query()->where('tenant_id', 2002)->count())->toBe(1)
        ->and($tokenService->calls)->toHaveCount(1)
        ->and($publishedPayloads)->toHaveCount(1)
        ->and($publishedPayloads[0])->toMatchArray([
            'type' => 'connection',
            'action' => 'logoutAll',
            'tenant_id' => 1001,
            'auth_collection' => 'superusers',
            'subscriber_id' => (string) $user->getKey(),
        ]);
});
