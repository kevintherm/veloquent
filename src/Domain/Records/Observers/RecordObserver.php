<?php

namespace Veloquent\Core\Domain\Records\Observers;

use Spatie\Multitenancy\Contracts\IsTenant;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;
use Veloquent\Core\Domain\Records\Services\FileFieldProcessor;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeDispatcher;
use Veloquent\Core\Domain\Records\Services\RelationIntegrityService;

class RecordObserver
{
    public function __construct(
        protected RealtimeDispatcher $dispatcher,
        protected RelationIntegrityService $integrityService,
        protected FileFieldProcessor $fileProcessor,
    ) {}

    public function creating(Record $record): void
    {
        //
    }

    public function created(Record $record): void
    {
        $this->publishEvent('created', $record);
    }

    public function updating(Record $record): void
    {
        //
    }

    public function updated(Record $record): void
    {
        $this->publishEvent('updated', $record);
    }

    public function deleting(Record $record): void
    {
        $this->integrityService->handleRecordDeletion($record->collection, $record->id);
        $this->fileProcessor->cleanupRecordFiles($record);

        $this->publishEvent('deleted', $record);
    }

    public function restored(Record $record): void
    {
        //
    }

    public function forceDeleted(Record $record): void
    {
        //
    }

    private function publishEvent(string $event, Record $record): void
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId === null) {
            return;
        }

        $collectionId = $record->collection?->id ?? $record->getAttribute('collection_id');
        if (! is_string($collectionId) || $collectionId === '') {
            return;
        }

        $realtimeEvent = new RealtimeRecordEvent(
            tenantId: $tenantId,
            collectionId: $collectionId,
            record: $record->toArray(),
            event: $event,
        );

        $this->dispatcher->handle($realtimeEvent);
    }

    private function resolveTenantId(): ?string
    {
        return data_get(app(IsTenant::class)::current(), 'id');
    }
}
