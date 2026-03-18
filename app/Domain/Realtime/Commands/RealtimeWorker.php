<?php

namespace App\Domain\Realtime\Commands;

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Realtime\Bus\RealtimeBusDriver;
use App\Domain\Records\Events\RecordChanged;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\CreateRuleContextBuilder;
use App\Models\RealtimeSubscription;
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
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$loadedCount): void {
                foreach ($chunk as $subscription) {
                    $this->subscriptions[$subscription->collection_id][] = [
                        'auth_collection' => $subscription->auth_collection,
                        'subscriber_id' => $subscription->subscriber_id,
                        'filter' => $subscription->filter ?? '',
                        'channel' => $subscription->channel,
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

        if (! is_string($collectionId) || $collectionId === '') {
            return;
        }

        if (($payload['action'] ?? null) === 'subscribe') {
            $authCollection = (string) ($payload['auth_collection'] ?? '');
            $subscriberId = (string) ($payload['subscriber_id'] ?? '');

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
            ];

            $this->debug('Realtime subscribe event applied', [
                'collection_id' => $collectionId,
                'auth_collection' => $authCollection,
                'subscriber_id' => $subscriberId,
                'channel' => (string) ($payload['channel'] ?? ''),
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

        $subscriptions = $this->subscriptions[$collectionId] ?? [];
        $matchedCount = 0;

        foreach ($subscriptions as $subscription) {
            if ($this->evaluateFilter((string) ($subscription['filter'] ?? ''), $collection, $record)) {
                $matchedCount++;

                broadcast(new RecordChanged(
                    channel: (string) $subscription['channel'],
                    event: $event,
                    record: $record,
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

    private function evaluateFilter(string $filter, Collection $collection, array $record): bool
    {
        if (blank($filter)) {
            return true;
        }

        try {
            $context = app(CreateRuleContextBuilder::class)
                ->build($collection, $record, null, app(Request::class));

            return QueryFilter::for(
                Record::of($collection)->newQuery(),
                array_keys($context)
            )->evaluate($filter, $context);
        } catch (\Throwable) {
            return false;
        }
    }

    private function debug(string $message, array $context = []): void
    {
        Log::debug('[realtime:worker] '.$message, $context);
    }
}
