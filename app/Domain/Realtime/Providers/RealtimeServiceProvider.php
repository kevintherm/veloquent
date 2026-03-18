<?php

namespace App\Domain\Realtime\Providers;

use App\Domain\Realtime\Bus\FilesystemRealtimeBus;
use App\Domain\Realtime\Bus\RealtimeBusDriver;
use App\Domain\Realtime\Bus\RedisRealtimeBus;
use App\Domain\Realtime\Commands\InstallRealtimeCron;
use App\Domain\Realtime\Commands\InstallRealtimeSupervisor;
use App\Domain\Realtime\Commands\RealtimeWorker;
use Illuminate\Support\ServiceProvider;

class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RealtimeBusDriver::class, function (): RealtimeBusDriver {
            $driver = config('velo.realtime.bus');

            return match ($driver) {
                'redis' => new RedisRealtimeBus,
                'filesystem' => new FilesystemRealtimeBus,
                default => throw new \InvalidArgumentException(
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
        ]);
    }
}
