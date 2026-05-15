<?php

namespace Veloquent\Core\Domain\Records\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Veloquent\Core\Domain\Hooks\HookRunner;
use Veloquent\Core\Domain\Hooks\HookPayload;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Collections\Models\Collection;

class DeleteRecordAction
{
    public function __construct(
        private readonly HookRunner $hookRunner,
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

        DB::transaction(function () use ($collection, $record, $authenticatedUser) {
            $this->hookRunner->run(new HookPayload(
                event: 'record.deleting',
                collection: $collection,
                record: $record,
                request: request(),
                actor: $authenticatedUser instanceof Record ? $authenticatedUser : null,
            ));

            $record->delete();
        });

        $this->hookRunner->run(new HookPayload(
            event: 'record.deleted',
            collection: $collection,
            record: $record,
            request: request(),
            actor: $authenticatedUser instanceof Record ? $authenticatedUser : null,
        ));
    }
}
