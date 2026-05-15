<?php

namespace Veloquent\Core\Domain\Realtime\Services;

use Throwable;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Landlord;
use Illuminate\Support\Facades\Log;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\RuleEngine\RuleEngine;
use Veloquent\Core\Infrastructure\Models\Tenant;
use Veloquent\Core\Domain\Records\Events\RecordChanged;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeBusDriver;
use Veloquent\Core\Domain\Realtime\Models\RealtimeSubscription;
use Veloquent\Core\Domain\Realtime\Jobs\ProcessRealtimeEventJob;
use Veloquent\Core\Domain\Records\Services\CreateRuleContextBuilder;

class RealtimeDispatcher
{
    public function __construct(
        protected CreateRuleContextBuilder $contextBuilder,
        protected RealtimeBusDriver $bus,
        protected RealtimeBuffer $buffer,
    ) {}

    /**
     * Handle a record event based on the configured strategy.
     */
    public function handle(RealtimeRecordEvent $event): void
    {
        $strategy = config('velo.realtime.strategy', 'worker');

        match ($strategy) {
            'sync' => $this->dispatch($event),
            'after_response' => $this->buffer->push($event),
            default => $this->publishToBus($event),
        };
    }

    /**
     * Publish the event to the external bus (for worker processing).
     */
    protected function publishToBus(RealtimeRecordEvent $event): void
    {
        Landlord::execute(function () use ($event) {
            try {
                $this->bus->publish($event->toArray());
            } catch (Throwable $e) {
                Log::error("[RealtimeDispatcher] Failed to publish event to bus", [
                    'error' => $e->getMessage(),
                    'tenant_id' => $event->tenantId,
                    'collection_id' => $event->collectionId,
                ]);
            }
        });
    }

    /**
     * Dispatch a realtime record event immediately.
     */
    public function dispatch(RealtimeRecordEvent $event, ?array $subscriptions = null, bool $shouldRetry = false): void
    {
        $tenantId = $event->tenantId;
        $collectionId = $event->collectionId;

        $tenant = Landlord::execute(fn() => Tenant::findByIdCached($tenantId));
        if (! $tenant) {
            Log::warning("[RealtimeDispatcher] Tenant not found.", ['tenant_id' => $tenantId]);
            return;
        }

        try {
            $collection = $tenant->execute(fn() => Collection::findByIdCached($collectionId));
            if (! $collection) {
                Log::warning("[RealtimeDispatcher] Collection not found.", ['collection_id' => $collectionId]);
                return;
            }

            $activeSubscriptions = $subscriptions ?? $this->loadSubscriptionsFromLandlord($tenantId, $collectionId);
            if (empty($activeSubscriptions)) {
                return;
            }

            $tenant->execute(function () use ($tenant, $collection, $activeSubscriptions, $event): void {
                foreach ($activeSubscriptions as $subscription) {
                    $this->processSubscription(
                        subscription: $subscription,
                        collection: $collection,
                        record: $event->record,
                        event: $event->event,
                        tenantId: $tenant->id
                    );
                }
            });
        } catch (Throwable $e) {
            Log::error("[RealtimeDispatcher] Dispatching error", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'collection_id' => $collectionId,
            ]);

            if (! $shouldRetry) {
                ProcessRealtimeEventJob::dispatch($event);
            }
        }
    }

    protected function loadSubscriptionsFromLandlord(int|string $tenantId, string $collectionId): array
    {
        return Landlord::execute(fn() => RealtimeSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('collection_id', $collectionId)
            ->where('expired_at', '>', now())
            ->get()
            ->map(fn($s) => [
                'auth_collection' => $s->auth_collection,
                'subscriber_id' => $s->subscriber_id,
                'filter' => $s->filter ?? '',
                'channel' => $s->channel,
                'expired_at' => $s->expired_at?->timestamp,
            ])
            ->toArray());
    }

    protected function processSubscription(array $subscription, Collection $collection, array $record, string $event, $tenantId): void
    {
        $filter = (string) ($subscription['filter'] ?? '');
        $matched = $this->evaluateFilter($filter, $collection, $record, $subscription);

        if ($matched) {
            try {
                broadcast(new RecordChanged(
                    channel: (string) $subscription['channel'],
                    event: $event,
                    record: [...$record, '_collection' => $collection->name],
                ));
            } catch (Throwable $e) {
                Log::warning("[RealtimeDispatcher] Broadcast failed, will retry via job", [
                    'error' => $e->getMessage(),
                    'channel' => $subscription['channel'],
                    'tenant_id' => $tenantId,
                ]);

                throw $e;
            }
        }
    }

    protected function evaluateFilter(string $filter, Collection $collection, array $record, array $subscription): bool
    {
        try {
            $subscriberCollection = Collection::findByNameCached($subscription['auth_collection']);
            if (! $subscriberCollection) {
                return false;
            }

            $subscriber = Record::of($subscriberCollection)
                ->newQuery()
                ->find($subscription['subscriber_id']);

            if (! $subscriber) {
                return false;
            }

            if ($subscriber->isSuperuser()) {
                return true;
            }

            $viewRule = $collection->api_rules['view'] ?? null;
            if ($viewRule === null) {
                return false;
            }

            $viewRule = trim($viewRule);
            if ($viewRule !== '') {
                $viewContext = $this->contextBuilder
                    ->build($collection, $record, $subscriber, Request::create('/'), $viewRule);

                $canView = RuleEngine::make(array_keys($viewContext))
                    ->evaluate($viewRule, $viewContext);

                if (! $canView) {
                    return false;
                }
            }

            if (blank($filter)) {
                return true;
            }

            $filterContext = $this->contextBuilder
                ->build($collection, $record, $subscriber, Request::create('/'), $filter);

            return RuleEngine::make(array_keys($filterContext))
                ->evaluate($filter, $filterContext);
        } catch (Throwable $e) {
            Log::debug('[RealtimeDispatcher] Evaluation error', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
