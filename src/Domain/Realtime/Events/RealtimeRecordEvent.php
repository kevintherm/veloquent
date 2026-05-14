<?php

namespace Veloquent\Core\Domain\Realtime\Events;

class RealtimeRecordEvent
{
    public function __construct(
        public int|string $tenantId,
        public string $collectionId,
        public array $record,
        public string $event = 'updated',
    ) {}

    /**
     * Create an instance from a raw array payload.
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            tenantId: $payload['tenant_id'],
            collectionId: $payload['collection_id'],
            record: $payload['record'],
            event: $payload['event'] ?? 'updated',
        );
    }

    /**
     * Convert the event to a raw array payload for the bus or queue.
     */
    public function toArray(): array
    {
        return [
            'type' => 'record_event',
            'tenant_id' => $this->tenantId,
            'collection_id' => $this->collectionId,
            'record' => $this->record,
            'event' => $this->event,
        ];
    }
}
