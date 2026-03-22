<?php

use App\Domain\Realtime\Providers\RealtimeServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\LogsServiceProvider;

return [
    AppServiceProvider::class,
    RealtimeServiceProvider::class,
    LogsServiceProvider::class,
];
