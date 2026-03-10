<?php

namespace App\Domain\Records\Actions;

use App\Domain\Records\Models\Record;

class UpdateRecordAction
{
    public function execute(Record $record, array $data): ?array
    {
        $record->update($data);

        return $record->fresh()->toArray();
    }
}
