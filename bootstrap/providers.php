<?php

use App\Domain\Realtime\Providers\RealtimeServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\LogsServiceProvider;
use App\Providers\RouteServiceProvider;

return [
    RealtimeServiceProvider::class,
    AppServiceProvider::class,
    LogsServiceProvider::class,
    RouteServiceProvider::class,
];
