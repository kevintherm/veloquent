<?php

namespace App\Domain\Realtime\Bus;

use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisRealtimeBus implements RealtimeBusDriver
{
    private const CHANNEL = 'realtime:events';

    private const CHANNEL_PATTERN = '*realtime:events';

    private const RECONNECT_DELAY_US = 500_000;

    public function publish(array $payload): void
    {
        $encodedPayload = json_encode($payload);

        if ($encodedPayload === false) {
            return;
        }

        Redis::connection('realtime')->publish(self::CHANNEL, $encodedPayload);
    }

    public function listen(callable $callback, Closure $shouldStop): void
    {
        $connection = Redis::connection('realtime');

        while (! $shouldStop()) {
            try {
                $client = $connection->client();

                if (method_exists($client, 'setOption')) {
                    $client->setOption(\Redis::OPT_READ_TIMEOUT, 2.0);
                }

                $connection->psubscribe(
                    [self::CHANNEL_PATTERN],
                    function (...$args) use ($callback, $shouldStop, $connection): void {
                        if ($shouldStop()) {
                            try {
                                $connection->punsubscribe();
                            } catch (\Throwable) {
                                //
                            }

                            return;
                        }

                        $message = null;

                        foreach ($args as $arg) {
                            if (is_string($arg) && ($arg[0] ?? null) === '{') {
                                $message = $arg;
                                break;
                            }
                        }

                        if (! is_string($message)) {
                            return;
                        }

                        $payload = json_decode($message, true);

                        if (is_array($payload)) {
                            $callback($payload);
                        }
                    }
                );
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $isTimeout = str_contains($message, 'read error on connection')
                    || str_contains($message, 'timeout');

                if (! $isTimeout) {
                    Log::warning('Realtime Redis listener failed. Retrying...', [
                        'message' => $message,
                    ]);
                }

                try {
                    $connection->disconnect();
                } catch (\Throwable) {
                }

                if (! $isTimeout) {
                    usleep(self::RECONNECT_DELAY_US);
                }
            }
        }
    }
}
