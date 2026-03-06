<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Database\QueryException;

class DeleteRecordAction
{
    public function execute(Collection $collection, string|int $recordId): bool
    {
        try {
            $record = Record::forCollection($collection);
            $existing = $record->find($recordId);

            if (! $existing) {
                return false;
            }

            $existing->delete();

            return true;
        } catch (QueryException $e) {
            throw new \Exception("Failed to delete record from table {$record->getTable()}: ".$e->getMessage());
        }
    }
}
