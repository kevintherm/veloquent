<?php

namespace App\Domain\Records\Observers;

use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\FileFieldProcessor;
use App\Domain\Records\Services\RelationIntegrityService;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Landlord;

class RecordObserver
{
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
        app(RelationIntegrityService::class)->handleRecordDeletion($record->collection, $record->id);
        app(FileFieldProcessor::class)->cleanupRecordFiles($record);

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

        Landlord::execute(function () use ($event, $tenantId, $collectionId, $record) {
            app(RealtimeBusDriver::class)->publish([
                'type' => 'record_event',
                'event' => $event,
                'tenant_id' => $tenantId,
                'collection_id' => $collectionId,
                'record' => $record->toArray(),
            ]);
        });
    }

    private function resolveTenantId(): ?string
    {
        return data_get(app(IsTenant::class)::current(), 'id');
    }
}
