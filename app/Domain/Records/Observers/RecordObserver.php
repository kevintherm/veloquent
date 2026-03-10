<?php

namespace App\Domain\Records\Observers;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Arr;
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
        //
    }

    public function updating(Record $record): void
    {
        //
    }

    public function updated(Record $record): void
    {
        //
    }

    public function deleted(Record $record): void
    {
        //
    }

    public function restored(Record $record): void
    {
        //
    }

    public function forceDeleted(Record $record): void
    {
        //
    }
}
