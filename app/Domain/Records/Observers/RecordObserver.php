<?php

namespace App\Domain\Records\Observers;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Realtime\Bus\RealtimeBusDriver;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordObserver
{
    public function creating(Record $record): void
    {
        if (
            $record->collection
            && $record->collection->type === CollectionType::Auth
            && Arr::first($record->collection->fields, fn ($field) => $field['name'] === 'token_key') !== null
        ) {
            $record->token_key = Str::random(64);
        }
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
        app(RealtimeBusDriver::class)->publish([
            'type' => 'record_event',
            'event' => $event,
            'collection_id' => $record->collection_id,
            'record' => $record->toArray(),
        ]);
    }
}
