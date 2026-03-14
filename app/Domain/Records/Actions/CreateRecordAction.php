<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Gate;

class CreateRecordAction
{
    public function execute(Collection $collection, array $data): array
    {
        Gate::authorize('create-records', $collection);

        $record = Record::of($collection);
        $created = $record->create($data);

        return $created->toArray();
    }
}
