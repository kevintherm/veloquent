<?php

namespace Veloquent\Core\Domain\SchemaManagement\Policies;

use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Infrastructure\Exceptions\InvalidArgumentException;

final class SchemaPolicy
{
    public function assertValidTableName(string $table): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException(
                "Invalid table name '{$table}'. Only letters and underscore allowed."
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertValidColumnName(string $column): void
    {
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException(
                "Invalid column name '{$column}'. Must start with a letter and only contain letters, numbers, and underscores."
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertValidColumnDefinition(array $column): void
    {
        if (! isset($column['name'], $column['type'])) {
            throw new InvalidArgumentException('Column must have name and type');
        }

        $type = CollectionFieldType::tryFrom($column['type']);
        if ($type === null) {
            throw new InvalidArgumentException('Invalid column type: '.$column['type']);
        }

        $this->assertValidColumnName($column['name']);
    }
}
