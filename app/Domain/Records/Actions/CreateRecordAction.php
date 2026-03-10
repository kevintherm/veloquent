<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;

class CreateRecordAction
{
    public function execute(Collection $collection, array $data): array
    {
        $record = Record::forCollection($collection);
        $created = $record->create($data);

        return $created->toArray();
    }
}
