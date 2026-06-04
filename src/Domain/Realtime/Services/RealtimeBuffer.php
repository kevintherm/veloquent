<?php

namespace Veloquent\Core\Domain\Realtime\Services;

use Veloquent\Core\Domain\Realtime\Contracts\RealtimeDispatcher;
use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;

class RealtimeBuffer
{
    /** @var RealtimeRecordEvent[] */
    private array $events = [];

    /**
     * Push an event to the buffer.
     */
    public function push(RealtimeRecordEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Flush the buffer and process all events using the dispatcher.
     */
    public function flush(RealtimeDispatcher $dispatcher): void
    {
        if ($this->isEmpty()) {
            return;
        }

        $eventsToProcess = $this->events;
        $this->events = [];

        foreach ($eventsToProcess as $event) {
            $dispatcher->dispatch($event);
        }
    }

    /**
     * Clear the buffer without processing.
     */
    public function clear(): void
    {
        $this->events = [];
    }

    /**
     * Check if the buffer is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->events);
    }
}
