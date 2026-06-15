<?php

namespace Veloquent\Core\Domain\Realtime\Providers;

use Veloquent\Core\Domain\Realtime\Bus\FilesystemRealtimeBus;
use Veloquent\Core\Domain\Realtime\Bus\RedisRealtimeBus;
use Veloquent\Core\Domain\Realtime\Commands\InstallRealtimeCron;
use Veloquent\Core\Domain\Realtime\Commands\InstallRealtimeSupervisor;
use Veloquent\Core\Domain\Realtime\Commands\PruneExpiredRealtimeSubscriptions;
use Veloquent\Core\Domain\Realtime\Commands\RealtimeWorker;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeBusDriver;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeDispatcher;
use Veloquent\Core\Domain\Realtime\Services\DefaultRealtimeDispatcher;
use Veloquent\Core\Domain\Realtime\Services\RealtimeBuffer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DefaultRealtimeDispatcher::class);
        $this->app->singletonIf(RealtimeDispatcher::class, function ($app) {
            return $app->make(DefaultRealtimeDispatcher::class);
        });
        $this->app->singleton(RealtimeBuffer::class);

        $this->app->singleton(RealtimeBusDriver::class, function ($app): RealtimeBusDriver {
            $driver = config('velo.realtime.bus', 'redis');

            if ($driver === 'redis' && ! class_exists('Redis')) {
                if (! $app->runningUnitTests()) {
                    Log::warning('Realtime bus configured for Redis, but Redis extension is not available. Falling back to filesystem bus.');
                }
                $driver = 'filesystem';
            }

            return match ($driver) {
                'redis' => new RedisRealtimeBus,
                'filesystem' => new FilesystemRealtimeBus,
                default => throw new InvalidArgumentException(
                    "Unknown realtime bus driver: [{$driver}]. Valid options: redis, filesystem"
                ),
            };
        });
    }

    public function boot(): void
    {
        $this->commands([
            RealtimeWorker::class,
            InstallRealtimeSupervisor::class,
            InstallRealtimeCron::class,
            PruneExpiredRealtimeSubscriptions::class,
        ]);

        if (config('velo.realtime.strategy') === 'after_response') {
            $this->app->terminating(function () {
                app(RealtimeBuffer::class)->flush(app(RealtimeDispatcher::class));
            });
        }
    }
}
