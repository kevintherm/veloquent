<?php

namespace Veloquent\Core\Domain\Realtime\Contracts;

use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;

interface RealtimeDispatcher
{
    /**
     * Handle a record event based on the configured strategy.
     */
    public function handle(RealtimeRecordEvent $event): void;

    /**
     * Dispatch a realtime record event immediately.
     */
    public function dispatch(RealtimeRecordEvent $event, ?array $subscriptions = null, bool $shouldRetry = false): void;
}
