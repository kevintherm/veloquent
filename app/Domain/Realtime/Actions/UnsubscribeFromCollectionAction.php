<?php

namespace App\Domain\Realtime\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Realtime\Models\RealtimeSubscription;
use App\Domain\Records\Models\Record;
use Spatie\Multitenancy\Contracts\IsTenant;

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
    }

    private function resolveTenantId(): string|null
    {
        return data_get(app(IsTenant::class)::current(), 'id');
    }
}
