<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;

class GetRecordsAction
{
    public function execute(Collection $collection, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $record = Record::forCollection($collection);
            $query = $record->newQuery();

            // @TODO: Handle operator AND & OR
            foreach ($filters as $field => $value) {
                if ($value !== null) {
                    $query->where($field, $value);
                }
            }

            return $query->paginate($perPage);
        } catch (QueryException $e) {
            throw new \Exception("Table {$record->getTable()} not found or inaccessible");
        }
    }
}
