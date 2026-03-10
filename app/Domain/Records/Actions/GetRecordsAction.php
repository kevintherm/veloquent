<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Pagination\LengthAwarePaginator;

class GetRecordsAction
{
    public function execute(Collection $collection, ?string $filters = '', int $perPage = 15): LengthAwarePaginator
    {
        $record = Record::forCollection($collection);
        $query = $record->newQuery();

        $query->filter($filters ?? '');

        $maxPerPage = config('velo.records_per_page_max');
        $perPage = $perPage > $maxPerPage ? $maxPerPage : $perPage;

        return $query->paginate($perPage);
    }
}
