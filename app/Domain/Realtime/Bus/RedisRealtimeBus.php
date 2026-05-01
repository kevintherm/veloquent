<?php

namespace App\Domain\Realtime\Bus;

use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisRealtimeBus implements RealtimeBusDriver
{
    private const STREAM = 'realtime:events';

    private const BLOCK_MS = 2000; // unblocks every 2s to check shouldStop()

    private const MAX_MESSAGES = 10;

    public function publish(array $payload): void
    {
        $encodedPayload = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);

        if ($encodedPayload === false) {
            Log::warning('Realtime payload JSON encoding failed.', [
                'error' => json_last_error_msg(),
            ]);

            return;
        }

        Redis::connection('realtime')->xadd(self::STREAM, '*', ['data' => $encodedPayload], 1000, true);
    }

    public function listen(callable $callback, Closure $shouldStop): void
    {
        $lastId = '$';

        while (! $shouldStop()) {
            try {
                $results = Redis::connection('realtime')->xread(
                    [self::STREAM => $lastId],
                    self::MAX_MESSAGES,
                    self::BLOCK_MS,
                );

                if (empty($results)) {
                    continue;
                }

                foreach ($results[self::STREAM] ?? [] as $id => $entry) {
                    $lastId = $id;

                    if (! isset($entry['data'])) {
                        continue;
                    }

                    $payload = json_decode($entry['data'], true);

                    if (is_array($payload)) {
                        $callback($payload);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Realtime Redis listener failed. Retrying...', [
                    'message' => $e->getMessage(),
                ]);

                try {
                    Redis::connection('realtime')->disconnect();
                } catch (\Throwable) {
                }

                usleep(500_000);
            }
        }
    }
}
