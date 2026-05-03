<?php

namespace Veloquent\Core\Domain\SchemaManagement\Exceptions;

use Veloquent\Core\Domain\SchemaManagement\Enums\SchemaOperation;
use RuntimeException;

class SchemaCorruptException extends RuntimeException
{
    public function __construct(
        public readonly string $collectionId,
        public readonly SchemaOperation $activity,
        public readonly string $tableName,
    ) {
        parent::__construct("Schema is corrupt for collection {$collectionId} (activity: {$activity->value})");
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error_type' => 'SCHEMA_CORRUPT',
            'message' => $this->getMessage(),
            'activity' => $this->activity->value,
            'collection_id' => $this->collectionId,
            'table_name' => $this->tableName,
        ], 409);
    }
}
