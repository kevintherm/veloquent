<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ShowRecordAction
{
    public function execute(Collection $collection, string $recordId): Record
    {
        Gate::authorize('view-records', $collection);

        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        $query = Record::of($collection);

        if (! $bypassApiRules) {
            $query->applyRule('view');
        }

        return $query->findOrFail($recordId);
    }
}
