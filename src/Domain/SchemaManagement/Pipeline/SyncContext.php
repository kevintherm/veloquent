<?php

namespace Veloquent\Core\Domain\SchemaManagement\Pipeline;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Support\TableName;
use Veloquent\Core\Domain\SchemaManagement\Enums\SchemaOperation;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;

final class SyncContext
{
    public ?SchemaChange $schemaChange = null;

    public function __construct(
        public readonly SchemaOperation $operation,
        public ?Collection $collection = null,
        public array $newFields = [],
        public array $newIndexes = [],
        public array $rawData = [],
    ) {}

    public function isCreate(): bool
    {
        return $this->operation === SchemaOperation::Create;
    }

    public function getTableName(): string
    {
        if ($this->collection) {
            return $this->collection->getPhysicalTableName();
        }

        return TableName::for($this->rawData['name'], $this->rawData['is_system'] ?? false);
    }
}
