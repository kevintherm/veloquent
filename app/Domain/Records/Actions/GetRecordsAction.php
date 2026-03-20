<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\RecordExpansionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GetRecordsAction
{
    public function __construct(private RecordExpansionService $recordExpansionService) {}

    public function execute(Collection $collection, string $sort, string $filter, int $perPage = 15, string $expand = ''): LengthAwarePaginator
    {
        Gate::authorize('list-records', $collection);

        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        $query = Record::of($collection)->newQuery();

        if (! $bypassApiRules) {
            $query->applyRule('list');
        }

        $query->applySorting($sort)->applyFilter($filter);

        $maxPerPage = config('velo.records_per_page_max');
        $perPage = max(1, min($perPage, $maxPerPage));

        $records = $query->paginate($perPage);

        $this->recordExpansionService->expandMany($collection, $records->items(), $expand);

        return $records;
    }
}
