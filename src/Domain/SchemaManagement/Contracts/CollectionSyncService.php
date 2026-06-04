<?php

namespace Veloquent\Core\Domain\SchemaManagement\Contracts;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Enums\SchemaOperation;

interface CollectionSyncService
{
    /**
     * Create a new database collection.
     */
    public function create(array $data): Collection;

    /**
     * Update an existing database collection schema.
     */
    public function update(Collection $collection, array $data): Collection;

    /**
     * Delete a database collection.
     */
    public function delete(Collection $collection): void;

    /**
     * Drop the physical tables/indexes associated with a collection.
     */
    public function drop(Collection $collection): void;

    /**
     * Sync schema structure/indexes of a collection based on its definition.
     */
    public function sync(Collection $collection, SchemaOperation $operation = SchemaOperation::Update): void;
}
