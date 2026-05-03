<?php

namespace Veloquent\Core\Domain\Records\Actions;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DeleteRecordAction
{
    public function execute(Collection $collection, string $recordId): void
    {
        Gate::authorize('delete-records', $collection);

        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        $query = Record::of($collection)->newQuery();

        if (! $bypassApiRules) {
            $query->applyRule('delete');
        }

        $record = $query->findOrFail($recordId);

        $record->delete();
    }
}
