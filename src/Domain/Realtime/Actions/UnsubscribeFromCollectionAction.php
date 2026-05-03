<?php

namespace Veloquent\Core\Domain\Realtime\Actions;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeBusDriver;
use Veloquent\Core\Domain\Realtime\Models\RealtimeSubscription;
use Veloquent\Core\Domain\Records\Models\Record;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Landlord;

class UnsubscribeFromCollectionAction
{
    public function __construct(private RealtimeBusDriver $driver) {}

    /**
     * Remove any realtime subscriptions for the given user and publish an
     * unsubscribe event.
     */
    public function execute(Record $user, Collection $collection): void
    {
        $tenantId = $this->resolveTenantId();
        $authCollection = $user->collection->name;
        $subscriberId = (string) $user->getKey();
        $channel = "private-$authCollection.$subscriberId";

        Landlord::execute(function () use ($tenantId, $collection, $authCollection, $subscriberId, $channel) {

            RealtimeSubscription::query()->where([
                'tenant_id' => $tenantId,
                'collection_id' => $collection->id,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
            ])->delete();

            $this->driver->publish([
                'type' => 'connection',
                'action' => 'unsubscribe',
                'tenant_id' => $tenantId,
                'collection_id' => $collection->id,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
                'channel' => $channel,
            ]);

        });
    }

    private function resolveTenantId(): ?string
    {
        return data_get(app(IsTenant::class)::current(), 'id');
    }
}
