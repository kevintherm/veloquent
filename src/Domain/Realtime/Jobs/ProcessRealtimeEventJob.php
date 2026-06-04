<?php

namespace Veloquent\Core\Domain\Realtime\Jobs;

use Veloquent\Core\Domain\Realtime\Contracts\RealtimeDispatcher;
use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRealtimeEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public RealtimeRecordEvent $event
    ) {}

    /**
     * Execute the job.
     */
    public function handle(RealtimeDispatcher $dispatcher): void
    {
        $dispatcher->dispatch($this->event, subscriptions: null, shouldRetry: true);
    }
}
