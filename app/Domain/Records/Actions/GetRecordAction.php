<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Database\QueryException;

class GetRecordAction
{
    public function execute(Collection $collection, string|int $recordId): ?array
    {
        try {
            $record = Record::forCollection($collection);
            $found = $record->find($recordId);

            return $found?->toArray();
        } catch (QueryException $e) {
            throw new \Exception("Collection {$collection->name} not found or inaccessible");
        }
    }
}
