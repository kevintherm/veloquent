<?php

namespace Veloquent\Core\Domain\Hooks;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;

class HookRunner
{
    public function __construct(
        private readonly HookRegistry $registry
    ) {}

    public function run(HookPayload $payload): HookPayload
    {
        $pipes = $this->registry->pipesFor($payload->event);

        if (empty($pipes)) {
            return $payload;
        }

        try {
            return app(Pipeline::class)
                ->send($payload)
                ->through($pipes)
                ->thenReturn();
        } catch (\Throwable $e) {
            if ($this->registry->isAfterEvent($payload->event)) {
                Log::error("Hook failed for event {$payload->event}: " . $e->getMessage(), [
                    'exception' => $e,
                    'event' => $payload->event,
                    'collection' => $payload->collection?->name,
                ]);

                return $payload;
            }

            throw $e;
        }
    }
}
