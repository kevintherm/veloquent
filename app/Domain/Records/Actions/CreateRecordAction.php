<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Gate;

class CreateRecordAction
{
    public function execute(Collection $collection, array $data): Record
    {
        Gate::authorize('create-records', $collection);

        return Record::of($collection)->create($data);
    }
}
