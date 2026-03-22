<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Models\Collection;
use App\Domain\SchemaManagement\Exceptions\SchemaCorruptException;
use App\Domain\SchemaManagement\Models\SchemaJob;

class SchemaCorruptGuard
{
    /**
     * Assert that the collection is not in a corrupt state.
     *
     * @throws SchemaCorruptException
     */
    public function assertNotCorrupt(Collection $collection): void
    {
        $job = SchemaJob::where('collection_id', $collection->id)->first();

        if ($job) {
            throw new SchemaCorruptException(
                collectionId: $job->collection_id,
                activity: $job->operation,
                tableName: $job->table_name
            );
        }
    }
}
