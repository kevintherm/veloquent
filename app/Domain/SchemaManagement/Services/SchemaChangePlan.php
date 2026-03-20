<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\ValueObjects\Field;
use App\Domain\SchemaManagement\Policies\SchemaPolicy;
use App\Infrastructure\Exceptions\InvalidArgumentException;

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

    /**
     * Diff $before and $after field arrays into a change plan.
     *
     * Fields are matched by their unique `id`. A changed name on the same id
     * is detected as a rename. A missing id in $after is a drop. A new id
     * in $after is an add. Attribute differences on the same id produce a
     * modify entry. Type changes are always rejected.
     *
     * @throws InvalidArgumentException|\LogicException
     */
    public static function buildPlan(array $before, array $after, bool $isAuthCollection = false): self
    {
        self::assertNoDuplicateFieldNames($after);

        $plan = new self;

        $beforeById = collect($before)->keyBy('id');
        $afterById = collect($after)->keyBy('id');

        foreach ($afterById as $id => $field) {
            if (! $beforeById->has($id)) {
                $plan->adds[] = $field;

                continue;
            }

            $oldField = $beforeById[$id];

            if ($oldField['name'] !== $field['name']) {
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
            if (! $afterById->has($id)) {
                $plan->drops[] = $field;
            }
        }

        $plan->validatePlan($plan, $isAuthCollection);

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
     * @throws \LogicException
     */
    private static function assertNoReservedFieldsInUserInput(array $fields, array $reservedNames): void
    {
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;

            if (is_string($name) && in_array($name, $reservedNames, true)) {
                throw new \LogicException("Reserved field '{$name}' cannot be defined manually.");
            }
        }
    }

    /**
     * @throws \LogicException
     */
    private static function assertNoDuplicateFieldNames(array $fields): void
    {
        $names = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? null;

            if ($name === null) {
                continue;
            }

            if (isset($names[$name])) {
                throw new \LogicException("Duplicate field name '{$name}' detected.");
            }

            $names[$name] = true;
        }
    }

    /**
     * @throws InvalidArgumentException|\LogicException
     */
    private function validatePlan(self $plan, bool $isAuthCollection = false): void
    {
        foreach ($plan->adds as $field) {
            $this->assertValidField($field);
        }

        foreach ($plan->modifies as [$old, $new]) {
            if ($isAuthCollection && in_array($old['name'], self::AUTH_RESERVED_FIELD_NAMES, true)) {
                throw new \LogicException("Cannot modify auth reserved field '{$old['name']}'.");
            }

            $this->assertValidField($new);
            $this->assertTypeNotChanged($old, $new);
        }

        foreach ($plan->drops as $field) {
            if ($isAuthCollection && in_array($field['name'], self::AUTH_RESERVED_FIELD_NAMES, true)) {
                throw new \LogicException("Cannot drop auth reserved field '{$field['name']}'.");
            }

            if ($field['required'] ?? false) {
                throw new \LogicException(
                    "Cannot drop required field '{$field['name']}'."
                );
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertValidField(array $field): void
    {
        app(SchemaPolicy::class)->assertValidColumnDefinition($field);
    }

    /**
     * @throws \LogicException
     */
    private function assertTypeNotChanged(array $old, array $new): void
    {
        $from = CollectionFieldType::tryFrom($old['type']);
        $to = CollectionFieldType::tryFrom($new['type']);

        if ($from !== $to) {
            throw new \LogicException(
                "Field type cannot be changed on field '{$new['name']}': '{$from->value}' to '{$to->value}' is not allowed."
            );
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
