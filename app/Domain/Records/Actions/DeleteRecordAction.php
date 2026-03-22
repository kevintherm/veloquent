<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\RelationIntegrityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DeleteRecordAction
{
    public function __construct(
        private readonly RelationIntegrityService $relationIntegrityService,
    ) {}

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

        $this->relationIntegrityService->handleRecordDeletion($collection, $recordId);

        $record->delete();
    }
}
