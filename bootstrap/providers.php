<?php

use App\Domain\Realtime\Providers\RealtimeServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\LogsServiceProvider;
use App\Providers\RouteServiceProvider;

return [
    AppServiceProvider::class,
    RouteServiceProvider::class,
    RealtimeServiceProvider::class,
    LogsServiceProvider::class,
];
