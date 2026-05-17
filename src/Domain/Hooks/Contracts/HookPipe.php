<?php

namespace Veloquent\Core\Domain\Hooks\Contracts;

use Closure;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;

interface HookPipe
{
    public function handle(HookPayload $payload, Closure $next): mixed;
}
