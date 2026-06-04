<?php

namespace Veloquent\Core\Domain\Realtime\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeBusDriver;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeDispatcher;
use Veloquent\Core\Domain\Realtime\Models\RealtimeSubscription;

class RealtimeWorker extends Command
{
    protected $signature = 'realtime:worker';

    protected $description = 'Process realtime subscription fan-out';

    private array $subscriptions = [];
    private RealtimeDispatcher $dispatcher;

    public function handle(RealtimeBusDriver $bus, RealtimeDispatcher $dispatcher): int
    {
        $this->dispatcher = $dispatcher;
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
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$loadedCount): void {
                foreach ($chunk as $subscription) {
                    $tenantId = $subscription->tenant_id;
                    $collectionId = $subscription->collection_id;

                    if (! $tenantId || $collectionId === '') {
                        continue;
                    }

                    $this->subscriptions[$tenantId][$collectionId][] = [
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
        $tenantId = $payload['tenant_id'] ?? null;
        $collectionId = $payload['collection_id'] ?? null;

        if (($payload['action'] ?? null) === 'logout' || ($payload['action'] ?? null) === 'logoutAll') {
            if ($tenantId === null) {
                return;
            }

            $this->handleLogout($payload, $tenantId);

            return;
        }

        if ($tenantId === null) {
            return;
        }

        if (! is_string($collectionId) || $collectionId === '') {
            return;
        }

        if (($payload['action'] ?? null) === 'subscribe') {
            $authCollection = (string) ($payload['auth_collection'] ?? '');
            $subscriberId = (string) ($payload['subscriber_id'] ?? '');
            $expiresAt = $this->parseExpiryTimestamp($payload['expired_at'] ?? null);

            $this->subscriptions[$tenantId][$collectionId] = array_values(array_filter(
                $this->subscriptions[$tenantId][$collectionId] ?? [],
                fn (array $subscription): bool => ! (
                    $subscription['auth_collection'] === $authCollection
                    && $subscription['subscriber_id'] === $subscriberId
                )
            ));

            $this->subscriptions[$tenantId][$collectionId][] = [
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
                'filter' => (string) ($payload['filter'] ?? ''),
                'channel' => (string) ($payload['channel'] ?? ''),
                'expired_at' => $expiresAt,
            ];

            $this->debug('Realtime subscribe event applied', [
                'tenant_id' => $tenantId,
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

            $this->subscriptions[$tenantId][$collectionId] = array_values(array_filter(
                $this->subscriptions[$tenantId][$collectionId] ?? [],
                fn (array $subscription): bool => ! (
                    $subscription['auth_collection'] === $authCollection
                    && $subscription['subscriber_id'] === $subscriberId
                )
            ));

            $this->debug('Realtime unsubscribe event applied', [
                'tenant_id' => $tenantId,
                'collection_id' => $collectionId,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
            ]);
        }
    }

    private function handleLogout(array $payload, int $tenantId): void
    {
        $subscriberId = (string) ($payload['subscriber_id'] ?? '');

        if ($subscriberId === '') {
            return;
        }

        $removedCount = 0;

        foreach (($this->subscriptions[$tenantId] ?? []) as $collectionId => $subscriptions) {
            $beforeCount = count($subscriptions);

            $this->subscriptions[$tenantId][$collectionId] = array_values(array_filter(
                $subscriptions,
                fn (array $subscription): bool => ! (
                    $subscription['subscriber_id'] === $subscriberId
                )
            ));

            $removedCount += max(0, $beforeCount - count($this->subscriptions[$tenantId][$collectionId]));
        }

        $this->debug('Realtime logout event applied', [
            'tenant_id' => $tenantId,
            'auth_collection' => (string) ($payload['auth_collection'] ?? ''),
            'subscriber_id' => $subscriberId,
            'removed_subscriptions' => $removedCount,
        ]);
    }

    private function handleRecordEvent(array $payload): void
    {
        $tenantId = $payload['tenant_id'] ?? null;
        $collectionId = $payload['collection_id'] ?? null;

        if ($tenantId === null || ! is_string($collectionId) || $collectionId === '') {
            return;
        }

        $subscriptions = $this->pruneExpiredSubscriptionsForCollection($tenantId, $collectionId);

        $this->dispatcher->dispatch(
            RealtimeRecordEvent::fromArray($payload),
            $subscriptions
        );
    }

    private function pruneExpiredSubscriptionsForCollection(int $tenantId, string $collectionId): array
    {
        $nowTimestamp = CarbonImmutable::now()->timestamp;
        $beforeCount = count($this->subscriptions[$tenantId][$collectionId] ?? []);

        $this->subscriptions[$tenantId][$collectionId] = array_values(array_filter(
            $this->subscriptions[$tenantId][$collectionId] ?? [],
            fn (array $subscription): bool => ! isset($subscription['expired_at'])
                || ! is_numeric($subscription['expired_at'])
                || (int) $subscription['expired_at'] > $nowTimestamp
        ));

        $afterCount = count($this->subscriptions[$tenantId][$collectionId]);
        $prunedCount = max(0, $beforeCount - $afterCount);

        if ($prunedCount > 0) {
            $this->debug('Realtime subscriptions pruned from memory', [
                'tenant_id' => $tenantId,
                'collection_id' => $collectionId,
                'pruned' => $prunedCount,
                'remaining' => $afterCount,
            ]);
        }

        return $this->subscriptions[$tenantId][$collectionId];
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



    private function debug(string $message, array $context = []): void
    {
        Log::debug('[realtime:worker] '.$message, $context);
    }
}
