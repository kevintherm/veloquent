<?php

namespace Veloquent\Core\Domain\Collections\Actions;

use Illuminate\Support\Facades\Cache;
use Veloquent\Core\Domain\Auth\Models\AuthToken;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Events\CollectionTruncated;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;

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
            $hashes = AuthToken::query()
                ->where('collection_id', $collection->id)
                ->pluck('token_hash')
                ->toArray();
            foreach ($hashes as $hash) {
                Cache::forget("velo:auth:{$hash}");
            }

            AuthToken::query()
                ->where('collection_id', $collection->id)
                ->delete();
        }

        return $deletedCount;
    }
}
