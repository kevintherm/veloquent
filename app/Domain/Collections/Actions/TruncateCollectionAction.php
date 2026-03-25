<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Auth\Models\AuthToken;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Events\CollectionTruncated;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;

class TruncateCollectionAction
{
    public function execute(Collection $collection): int
    {
        $deletedCount = 0;

        Record::of($collection)->chunkById(500, function (\Illuminate\Database\Eloquent\Collection $records) use (&$deletedCount) {
            foreach ($records as $record) {
                $record->delete();
                $deletedCount++;
            }
        });

        CollectionTruncated::dispatch($collection, $deletedCount);

        if ($collection->type === CollectionType::Auth) {
            AuthToken::query()
                ->where('collection_id', $collection->id)
                ->delete();
        }

        return $deletedCount;
    }
}
