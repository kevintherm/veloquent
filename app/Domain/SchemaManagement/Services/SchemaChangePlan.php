<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\ValueObjects\Field;
use InvalidArgumentException;

final class SchemaChangePlan
{
    /**
     * Base reserved field names that are auto-managed and should be ignored from requests.
     */
    private const BASE_RESERVED_FIELD_NAMES = ['id', 'created_at', 'updated_at'];

    /**
     * Auth-specific reserved field names that cannot be modified or deleted.
     */
    private const AUTH_RESERVED_FIELD_NAMES = ['email', 'password', 'email_visibility', 'verified'];

    /**
     * Get auth reserved field names.
     */
    public static function getAuthReservedFields(): array
    {
        return self::AUTH_RESERVED_FIELD_NAMES;
    }

    /**
     * Get all reserved field names for a given collection type.
     */
    public static function getAllReservedFields(bool $isAuthCollection = false): array
    {
        $fields = self::BASE_RESERVED_FIELD_NAMES;

        if ($isAuthCollection) {
            $fields = [...$fields, ...self::AUTH_RESERVED_FIELD_NAMES];
        }

        return $fields;
    }

    /**
     * Generate the physical table name for a collection.
     */
    public static function generateTableName(string $collectionName, bool $isSystem = false): string
    {
        if ($isSystem) {
            return $collectionName;
        }

        $prefix = config('velo.collection_prefix');

        return $prefix.$collectionName;
    }

    /**
     * Get base reserved fields with proper metadata for storage.
     */
    public static function getSystemFields(): array
    {
        return [
            self::normalizeFieldDefinition([
                'name' => 'id',
                'type' => CollectionFieldType::Text->value,
                'nullable' => false,
                'unique' => true,
            ]),
            self::normalizeFieldDefinition([
                'name' => 'created_at',
                'type' => CollectionFieldType::Datetime->value,
                'nullable' => false,
                'unique' => false,
            ]),
            self::normalizeFieldDefinition([
                'name' => 'updated_at',
                'type' => CollectionFieldType::Datetime->value,
                'nullable' => false,
                'unique' => false,
            ]),
        ];
    }

    /**
     * Get auth reserved fields with proper metadata for storage.
     */
    public static function getAuthSystemFields(): array
    {
        return [
            self::normalizeFieldDefinition([
                'name' => 'email',
                'type' => CollectionFieldType::Email->value,
                'nullable' => false,
                'unique' => true,
            ]),
            self::normalizeFieldDefinition([
                'name' => 'password',
                'type' => CollectionFieldType::Text->value,
                'nullable' => false,
                'unique' => false,
            ]),
            self::normalizeFieldDefinition([
                'name' => 'email_visibility',
                'type' => CollectionFieldType::Boolean->value,
                'nullable' => true,
                'unique' => false,
                'default' => true,
            ]),
            self::normalizeFieldDefinition([
                'name' => 'verified',
                'type' => CollectionFieldType::Boolean->value,
                'nullable' => true,
                'unique' => false,
                'default' => false,
            ]),
        ];
    }

    /**
     * Merge system fields with user fields for metadata storage.
     * Places id first, auth system fields second (for auth collections), user fields next, and timestamps last.
     *
     * Validates that no user-submitted field uses a reserved name, then injects
     * the canonical reserved field definitions. Assigns a unique field ID to
     * any field that does not already have one.
     *
     * @throws \LogicException
     */
    public static function mergeWithSystemFields(array $userFields, bool $isAuthCollection = false): array
    {
        $reservedNames = array_keys(self::getReservedFieldDefinitions($isAuthCollection));

        $normalizedUserFields = collect($userFields)
            ->map(fn (array|Field $field) => self::normalizeInputField($field))
            ->values()
            ->all();

        self::assertNoReservedFieldsInUserInput($normalizedUserFields, $reservedNames);

        $reservedDefinitions = self::getReservedFieldDefinitions($isAuthCollection);

        $merged = [
            $reservedDefinitions['id'],
            ...($isAuthCollection ? self::getAuthSystemFields() : []),
            ...$normalizedUserFields,
            $reservedDefinitions['created_at'],
            $reservedDefinitions['updated_at'],
        ];

        return collect($merged)
            ->values()
            ->map(function (array $field, int $index): array {
                $field['order'] = $index;
                $field['id'] ??= self::generateFieldId();

                return $field;
            })
            ->all();
    }

    /**
     * Get canonical reserved field definitions keyed by field name.
     */
    public static function getReservedFieldDefinitions(bool $isAuthCollection = false): array
    {
        $base = collect(self::getSystemFields())->keyBy('name')->all();

        if (! $isAuthCollection) {
            return $base;
        }

        return [
            ...$base,
            ...collect(self::getAuthSystemFields())->keyBy('name')->all(),
        ];
    }

    /**
     * Prepare fields for DDL operations by stripping base reserved fields and metadata-only properties.
     */
    public static function stripForDDL(array $fields): array
    {
        return collect($fields)
            ->map(fn (array|Field $field) => self::normalizeInputField($field))
            ->reject(fn (array $field) => in_array($field['name'], self::BASE_RESERVED_FIELD_NAMES, true))
            ->map(function (array $field): array {
                unset($field['order']);

                return $field;
            })
            ->values()
            ->all();
    }

    public function __construct(
        public array $renames = [],
        public array $adds = [],
        public array $modifies = [],
        public array $drops = [],
    ) {}

    public static function buildPlan(array $before, array $after, bool $isAuthCollection = false): self
    {
        $plan = new self;

        $beforeById = collect($before)
            ->filter(function (array $field): bool {
                $id = $field['id'] ?? null;

                return is_string($id) && $id !== '';
            })
            ->keyBy('id');

        $matchedBeforeIds = [];

        foreach (array_values($after) as $field) {
            $fieldId = $field['id'] ?? null;

            if (! is_string($fieldId) || $fieldId === '' || ! $beforeById->has($fieldId)) {
                $field['id'] = self::generateFieldId();
                $plan->adds[] = $field;

                continue;
            }

            $matchedBeforeIds[$fieldId] = true;
            $oldField = $beforeById->get($fieldId);

            if (($oldField['name'] ?? null) !== ($field['name'] ?? null)) {
                $plan->renames[] = [$oldField['name'], $field['name']];
            }

            $comparableOld = $oldField;
            $comparableNew = $field;
            unset($comparableOld['order'], $comparableNew['order']);

            if ($comparableOld !== $comparableNew) {
                $plan->modifies[] = [$oldField, $field];
            }
        }

        foreach ($beforeById as $id => $field) {
            if (isset($matchedBeforeIds[$id])) {
                continue;
            }

            $plan->drops[] = $field;
        }

        return $plan;
    }

    /**
     * Generate an 8-character hexadecimal field ID.
     */
    public static function generateFieldId(): string
    {
        return bin2hex(random_bytes(4));
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertNoReservedFieldsInUserInput(array $fields, array $reservedNames): void
    {
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;

            if (is_string($name) && in_array($name, $reservedNames, true)) {
                throw new InvalidArgumentException("Reserved field '{$name}' cannot be defined manually.");
            }
        }
    }

    private static function normalizeFieldDefinition(array $field): array
    {
        $type = CollectionFieldType::from($field['type']);

        return [
            ...$type->defaultShape(),
            ...$field,
            'type' => $type->value,
        ];
    }

    private static function normalizeInputField(array|Field $field): array
    {
        if ($field instanceof Field) {
            return $field->toArray();
        }

        return self::normalizeFieldDefinition($field);
    }
}
