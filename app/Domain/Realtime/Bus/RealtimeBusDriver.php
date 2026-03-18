<?php

namespace App\Domain\Realtime\Bus;

use Closure;

interface RealtimeBusDriver
{
    public function publish(array $payload): void;

    public function listen(callable $callback, Closure $shouldStop): void;
}
