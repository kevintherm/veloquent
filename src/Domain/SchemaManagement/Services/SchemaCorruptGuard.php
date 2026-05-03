<?php

namespace Veloquent\Core\Domain\SchemaManagement\Services;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Exceptions\SchemaCorruptException;
use Veloquent\Core\Domain\SchemaManagement\Models\SchemaJob;

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
