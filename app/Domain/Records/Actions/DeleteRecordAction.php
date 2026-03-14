<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Gate;

class DeleteRecordAction
{
    public function execute(Collection $collection, string $recordId): void
    {
        Gate::authorize('delete-records', $collection);

        Record::of($collection)
            ->applyRule('delete')
            ->findOrFail($recordId)
            ->delete();
    }
}
