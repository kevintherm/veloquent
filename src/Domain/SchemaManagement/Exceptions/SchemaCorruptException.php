<?php

namespace Veloquent\Core\Domain\SchemaManagement\Exceptions;

use Veloquent\Core\Domain\SchemaManagement\Enums\SchemaOperation;
use Veloquent\Core\Infrastructure\Exceptions\VeloquentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchemaCorruptException extends VeloquentException
{
    public function __construct(
        public readonly string $collectionId,
        public readonly SchemaOperation $activity,
        public readonly string $tableName,
    ) {
        parent::__construct("Schema is corrupt for collection {$collectionId} (activity: {$activity->value})");
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'SCHEMA_CORRUPT';
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => $this->errorCode(),
            'error_type' => $this->errorCode(),
            'message' => $this->getMessage(),
            'activity' => $this->activity->value,
            'collection_id' => $this->collectionId,
            'table_name' => $this->tableName,
        ], $this->httpStatus());
    }
}
