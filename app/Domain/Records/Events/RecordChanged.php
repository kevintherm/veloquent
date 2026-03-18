<?php

namespace App\Domain\Records\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class RecordChanged implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        private readonly string $channel,
        public readonly string $event,
        public readonly array $record,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        $channelName = str_starts_with($this->channel, 'private-')
            ? substr($this->channel, 8)
            : $this->channel;

        return new PrivateChannel($channelName);
    }

    public function broadcastAs(): string
    {
        return "record.{$this->event}";
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'record' => $this->record,
        ];
    }
}
