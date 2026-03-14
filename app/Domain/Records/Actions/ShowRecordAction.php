<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Gate;

class ShowRecordAction
{
    public function execute(Collection $collection, string $recordId): array
    {
        Gate::authorize('view-records', $collection);
        
        $record = Record::of($collection)
            ->applyRule('view')
            ->findOrFail($recordId);
            
        return $record->toArray();
    }
}
