<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class GetRecordsAction
{
    public function execute(Collection $collection, ?string $filters = null, int $perPage = 15): LengthAwarePaginator
    {
        Gate::authorize('list-records', $collection);

        $record = Record::of($collection)
            ->applyRule('list')
            ->applyFilter($filters ?? '');

        $maxPerPage = config('velo.records_per_page_max');
        $perPage = max(0, min($perPage, $maxPerPage));

        return $record->paginate($perPage);
    }
}
