<?php

namespace App\Domain\Realtime\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Realtime\Models\RealtimeSubscription;
use App\Domain\Records\Models\Record;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Landlord;

class SubscribeToCollectionAction
{
    public function __construct(private RealtimeBusDriver $driver) {}

    /**
     * Create or update a realtime subscription and publish the subscribe event.
     * Returns an array with channel and expires_at for immediate response.
     */
    public function execute(Record $user, Collection $collection, ?string $filter = null): array
    {
        $tenantId = $this->resolveTenantId();
        $authCollection = $user->collection->name;
        $subscriberId = (string) $user->getKey();
        $channel = "private-{$authCollection}.{$subscriberId}";
        $subscriptionTtl = max(1, (int) config('velo.realtime.subscription_ttl', 120));
        $expiresAt = CarbonImmutable::now()->addSeconds($subscriptionTtl);

        Landlord::execute(function () use ($tenantId, $collection, $authCollection, $subscriberId, $filter, $channel, $expiresAt) {

            $subscription = RealtimeSubscription::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'collection_id' => $collection->id,
                    'auth_collection' => $authCollection,
                    'subscriber_id' => $subscriberId,
                ],
                [
                    'id' => (string) Str::ulid(),
                    'channel' => $channel,
                    'filter' => (string) ($filter ?? ''),
                    'expired_at' => $expiresAt,
                ]
            );

            $this->driver->publish([
                'type' => 'connection',
                'action' => 'subscribe',
                'tenant_id' => $tenantId,
                'collection_id' => $collection->id,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
                'filter' => $subscription->filter,
                'channel' => $channel,
                'expired_at' => $expiresAt->toIso8601String(),
            ]);

        });

        return [
            'channel' => $channel,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function resolveTenantId(): int
    {
        $tenant = app(IsTenant::class)::current();
        $tenantId = data_get($tenant, 'id');

        if (! is_numeric($tenantId)) {
            throw new RuntimeException('No current tenant is available for realtime subscriptions.');
        }

        return (int) $tenantId;
    }
}
