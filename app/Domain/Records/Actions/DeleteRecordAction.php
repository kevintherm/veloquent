<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DeleteRecordAction
{
    public function execute(Collection $collection, string $recordId): void
    {
        Gate::authorize('delete-records', $collection);

        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        $query = Record::of($collection);

        if (! $bypassApiRules) {
            $query->applyRule('delete');
        }

        $query->findOrFail($recordId)
            ->delete();
    }
}
