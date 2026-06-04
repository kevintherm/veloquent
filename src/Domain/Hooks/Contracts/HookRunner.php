<?php

namespace Veloquent\Core\Domain\Hooks\Contracts;

use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;

interface HookRunner
{
    /**
     * Run the payload through the registered hooks for the event.
     */
    public function run(HookPayload $payload): HookPayload;
}
