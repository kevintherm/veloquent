<?php

namespace Veloquent\Core\Domain\Realtime\Contracts;

use Closure;

interface RealtimeBusDriver
{
    public function publish(array $payload): void;

    public function listen(callable $callback, Closure $shouldStop): void;
}
