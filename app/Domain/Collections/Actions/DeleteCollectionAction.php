<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;

class DeleteCollectionAction
{
    public function execute(Collection $collection): void
    {
        Record::of($collection)->chunkById(500, function (\Illuminate\Database\Eloquent\Collection $records) {
            foreach ($records as $record) {
                $record->delete();
            }
        });

        $collection->delete();
    }
}
