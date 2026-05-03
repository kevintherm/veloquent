<?php

namespace Veloquent\Core\Domain\Records\Actions;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Records\Services\RecordExpansionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ShowRecordAction
{
    public function __construct(private RecordExpansionService $recordExpansionService) {}

    public function execute(Collection $collection, string $recordId, string $expand = ''): Record
    {
        Gate::authorize('view-records', $collection);

        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        $query = Record::of($collection)->newQuery();

        if (! $bypassApiRules) {
            $query->applyRule('view');
        }

        $record = $query->findOrFail($recordId);

        $this->recordExpansionService->expandMany($collection, [$record], $expand);

        return $record;
    }
}
