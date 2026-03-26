<?php

namespace App\Domain\Realtime\Providers;

use App\Domain\Realtime\Bus\FilesystemRealtimeBus;
use App\Domain\Realtime\Bus\RedisRealtimeBus;
use App\Domain\Realtime\Commands\InstallRealtimeCron;
use App\Domain\Realtime\Commands\InstallRealtimeSupervisor;
use App\Domain\Realtime\Commands\PruneExpiredRealtimeSubscriptions;
use App\Domain\Realtime\Commands\RealtimeWorker;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RealtimeBusDriver::class, function (): RealtimeBusDriver {
            $driver = config('velo.realtime.bus', 'redis');

            if ($driver === 'redis' && ! class_exists('Redis')) {
                Log::warning('Realtime bus configured for Redis, but Redis extension is not available. Falling back to filesystem bus.');
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
    }
}
