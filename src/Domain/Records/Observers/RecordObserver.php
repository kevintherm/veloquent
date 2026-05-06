<?php

namespace Veloquent\Core\Domain\Records\Observers;

use Veloquent\Core\Domain\Realtime\Contracts\RealtimeBusDriver;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Records\Services\FileFieldProcessor;
use Veloquent\Core\Domain\Records\Services\RelationIntegrityService;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Landlord;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            try {
                app(RealtimeBusDriver::class)->publish([
                    'type' => 'record_event',
                    'event' => $event,
                    'tenant_id' => $tenantId,
                    'collection_id' => $collectionId,
                    'record' => $record->toArray(),
                ]);
            } catch (Throwable $e) {
                Log::error("Failed to publish {$event} event for record {$record->id}: {$e->getMessage()}", [
                    'exception' => $e,
                    'tenant_id' => $tenantId,
                    'collection_id' => $collectionId,
                ]);
            }
        });
    }

    private function resolveTenantId(): ?string
    {
        return data_get(app(IsTenant::class)::current(), 'id');
    }
}
