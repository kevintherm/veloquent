<?php

namespace App\Domain\Realtime\Commands;

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Realtime\Models\RealtimeSubscription;
use App\Domain\Records\Events\RecordChanged;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\CreateRuleContextBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RealtimeWorker extends Command
{
    protected $signature = 'realtime:worker';

    protected $description = 'Process realtime subscription fan-out';

    private array $subscriptions = [];

    public function handle(RealtimeBusDriver $bus): int
    {
        $mode = config('velo.realtime.mode', 'persistent');
        $ttl = (int) config('velo.realtime.cron_ttl', 55);
        $startedAt = time();

        $this->info('[realtime:worker] Loading subscriptions...');
        $this->loadSubscriptions();
        $this->info('[realtime:worker] Listening via ['.config('velo.realtime.bus').'] driver...');

        $bus->listen(
            callback: fn (array $payload) => $this->dispatchPayload($payload),
            shouldStop: fn (): bool => $mode === 'cron' && (time() - $startedAt) >= $ttl,
        );

        $this->info('[realtime:worker] Shutting down cleanly.');

        return self::SUCCESS;
    }

    private function loadSubscriptions(): void
    {
        $loadedCount = 0;

        RealtimeSubscription::query()
            ->where('expired_at', '>', now())
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$loadedCount): void {
                foreach ($chunk as $subscription) {
                    $this->subscriptions[$subscription->collection_id][] = [
                        'auth_collection' => $subscription->auth_collection,
                        'subscriber_id' => $subscription->subscriber_id,
                        'filter' => $subscription->filter ?? '',
                        'channel' => $subscription->channel,
                        'expired_at' => $subscription->expired_at?->timestamp,
                    ];

                    $loadedCount++;
                }
            });

        $this->debug('Loaded subscriptions from DB', [
            'count' => $loadedCount,
        ]);
    }

    private function dispatchPayload(array $payload): void
    {
        match ($payload['type'] ?? null) {
            'connection' => $this->handleConnection($payload),
            'record_event' => $this->handleRecordEvent($payload),
            default => null,
        };
    }

    private function handleConnection(array $payload): void
    {
        $collectionId = $payload['collection_id'] ?? null;

        if (($payload['action'] ?? null) === 'logout') {
            $this->handleLogout($payload);

            return;
        }

        if (! is_string($collectionId) || $collectionId === '') {
            return;
        }

        if (($payload['action'] ?? null) === 'subscribe') {
            $authCollection = (string) ($payload['auth_collection'] ?? '');
            $subscriberId = (string) ($payload['subscriber_id'] ?? '');
            $expiresAt = $this->parseExpiryTimestamp($payload['expired_at'] ?? null);

            $this->subscriptions[$collectionId] = array_values(array_filter(
                $this->subscriptions[$collectionId] ?? [],
                fn (array $subscription): bool => ! (
                    $subscription['auth_collection'] === $authCollection
                    && $subscription['subscriber_id'] === $subscriberId
                )
            ));

            $this->subscriptions[$collectionId][] = [
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
                'filter' => (string) ($payload['filter'] ?? ''),
                'channel' => (string) ($payload['channel'] ?? ''),
                'expired_at' => $expiresAt,
            ];

            $this->debug('Realtime subscribe event applied', [
                'collection_id' => $collectionId,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
                'channel' => (string) ($payload['channel'] ?? ''),
                'expired_at' => $expiresAt,
            ]);

            return;
        }

        if (($payload['action'] ?? null) === 'unsubscribe') {
            $authCollection = (string) ($payload['auth_collection'] ?? '');
            $subscriberId = (string) ($payload['subscriber_id'] ?? '');

            $this->subscriptions[$collectionId] = array_values(array_filter(
                $this->subscriptions[$collectionId] ?? [],
                fn (array $subscription): bool => ! (
                    $subscription['auth_collection'] === $authCollection
                    && $subscription['subscriber_id'] === $subscriberId
                )
            ));

            $this->debug('Realtime unsubscribe event applied', [
                'collection_id' => $collectionId,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
            ]);
        }
    }

    private function handleLogout(array $payload): void
    {
        $subscriberId = (string) ($payload['subscriber_id'] ?? '');

        if ($subscriberId === '') {
            return;
        }

        $removedCount = 0;

        foreach ($this->subscriptions as $collectionId => $subscriptions) {
            $beforeCount = count($subscriptions);

            $this->subscriptions[$collectionId] = array_values(array_filter(
                $subscriptions,
                fn (array $subscription): bool => ! (
                    $subscription['subscriber_id'] === $subscriberId
                )
            ));

            $removedCount += max(0, $beforeCount - count($this->subscriptions[$collectionId]));
        }

        $this->debug('Realtime logout event applied', [
            'auth_collection' => (string) ($payload['auth_collection'] ?? ''),
            'subscriber_id' => $subscriberId,
            'removed_subscriptions' => $removedCount,
        ]);
    }

    private function handleRecordEvent(array $payload): void
    {
        $collectionId = $payload['collection_id'] ?? null;
        $record = $payload['record'] ?? [];
        $event = (string) ($payload['event'] ?? 'updated');

        if (! is_string($collectionId) || $collectionId === '' || ! is_array($record) || $record === []) {
            return;
        }

        $collection = Collection::query()->find($collectionId);

        if (! $collection) {
            return;
        }

        $subscriptions = $this->pruneExpiredSubscriptionsForCollection($collectionId);
        $matchedCount = 0;

        foreach ($subscriptions as $subscription) {
            $filter = (string) ($subscription['filter'] ?? '');
            $matched = $this->evaluateFilter($filter, $collection, $record, $subscription);

            $this->debug('Realtime subscription filter/auth evaluated', [
                'event' => $event,
                'collection_id' => $collectionId,
                'auth_collection' => (string) ($subscription['auth_collection'] ?? ''),
                'subscriber_id' => (string) ($subscription['subscriber_id'] ?? ''),
                'channel' => (string) ($subscription['channel'] ?? ''),
                'filter' => $filter,
                'matched' => $matched,
                'record_id' => $record['id'] ?? null,
            ]);

            if ($matched) {
                $matchedCount++;

                broadcast(new RecordChanged(
                    channel: (string) $subscription['channel'],
                    event: $event,
                    record: array_intersect_key($record, array_flip(array_keys($record))),
                ));
            }
        }

        $this->debug('Realtime record event processed', [
            'event' => $event,
            'collection_id' => $collectionId,
            'subscriptions_in_collection' => count($subscriptions),
            'matched_subscriptions' => $matchedCount,
            'record_id' => $record['id'] ?? null,
        ]);
    }

    private function pruneExpiredSubscriptionsForCollection(string $collectionId): array
    {
        $nowTimestamp = CarbonImmutable::now()->timestamp;
        $beforeCount = count($this->subscriptions[$collectionId] ?? []);

        $this->subscriptions[$collectionId] = array_values(array_filter(
            $this->subscriptions[$collectionId] ?? [],
            fn (array $subscription): bool => ! isset($subscription['expired_at'])
                || ! is_numeric($subscription['expired_at'])
                || (int) $subscription['expired_at'] > $nowTimestamp
        ));

        $afterCount = count($this->subscriptions[$collectionId]);
        $prunedCount = max(0, $beforeCount - $afterCount);

        if ($prunedCount > 0) {
            $this->debug('Realtime subscriptions pruned from memory', [
                'collection_id' => $collectionId,
                'pruned' => $prunedCount,
                'remaining' => $afterCount,
            ]);
        }

        return $this->subscriptions[$collectionId];
    }

    private function parseExpiryTimestamp(mixed $expiredAt): int
    {
        if (is_string($expiredAt) && $expiredAt !== '') {
            try {
                return CarbonImmutable::parse($expiredAt)->timestamp;
            } catch (\Throwable) {
                // Fall through to the default TTL based expiry.
            }
        }

        $defaultTtl = max(1, (int) config('velo.realtime.subscription_ttl', 120));

        return CarbonImmutable::now()->addSeconds($defaultTtl)->timestamp;
    }

    private function evaluateFilter(string $filter, Collection $collection, array $record, array $subscription): bool
    {
        try {
            $subscriberCollection = Collection::query()->where('name', $subscription['auth_collection'])->first();
            if (! $subscriberCollection) {
                return false;
            }

            $subscriber = Record::of($subscriberCollection)
                ->newQuery()
                ->find($subscription['subscriber_id']);

            if (! $subscriber) {
                return false;
            }

            $viewRule = $collection->api_rules['view'] ?? null;
            if ($viewRule === null) {
                return false;
            }

            $viewRule = trim($viewRule);
            if ($viewRule !== '') {
                $viewContext = app(CreateRuleContextBuilder::class)
                    ->build($collection, $record, $subscriber, Request::create('/'), $viewRule);

                $canView = QueryFilter::for(Record::of($collection)->newQuery(), array_keys($viewContext))
                    ->evaluate($viewRule, $viewContext);

                if (! $canView) {
                    return false;
                }
            }
            if (blank($filter)) {
                return true;
            }

            $filterContext = app(CreateRuleContextBuilder::class)
                ->build($collection, $record, $subscriber, Request::create('/'), $filter);

            return QueryFilter::for(
                Record::of($collection)->newQuery(),
                array_keys($filterContext)
            )->evaluate($filter, $filterContext);
        } catch (\Throwable $e) {
            $this->debug('Evaluation error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function debug(string $message, array $context = []): void
    {
        Log::debug('[realtime:worker] '.$message, $context);
    }
}
