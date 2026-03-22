<?php

namespace App\Domain\SchemaManagement\Exceptions;

use App\Domain\SchemaManagement\Enums\SchemaOperation;
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
}
