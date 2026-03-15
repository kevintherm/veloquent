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
    private const AUTH_RESERVED_FIELD_NAMES = ['email', 'password', 'email_visibility', 'verified', 'token_key'];

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
                'length' => 26,
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
                'length' => 255,
            ]),
            self::normalizeFieldDefinition([
                'name' => 'password',
                'type' => CollectionFieldType::Text->value,
                'nullable' => false,
                'unique' => false,
                'length' => 255,
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
            self::normalizeFieldDefinition([
                'name' => 'token_key',
                'type' => CollectionFieldType::Text->value,
                'nullable' => false,
                'unique' => false,
                'length' => 255,
            ]),
        ];
    }

    /**
     * Merge system fields with user fields for metadata storage.
     * Places id first, auth system fields second (for auth collections), user fields next, and timestamps last.
     */
    public static function mergeWithSystemFields(array $userFields, bool $isAuthCollection = false): array
    {
        $reservedDefinitions = self::getReservedFieldDefinitions($isAuthCollection);
        $reservedNames = array_keys($reservedDefinitions);

        $normalizedUserFields = collect($userFields)
            ->map(fn (array|Field $field) => self::normalizeInputField($field))
            ->reject(fn (array $field) => in_array($field['name'], $reservedNames, true))
            ->values()
            ->all();

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
                unset($field['order'], $field['collection']);

                return $field;
            })
            ->values()
            ->all();
    }

    /**
     * Type changes that are always safe (no data loss, no cast ambiguity).
     *
     * @return array<string, CollectionFieldType[]>
     */
    private static function compatibleTypeChanges(): array
    {
        return [
            CollectionFieldType::Text->value => [CollectionFieldType::Text, CollectionFieldType::LongText],
            CollectionFieldType::LongText->value => [CollectionFieldType::LongText, CollectionFieldType::Text],
            CollectionFieldType::Number->value => [CollectionFieldType::Number],
            CollectionFieldType::Boolean->value => [CollectionFieldType::Boolean],
            CollectionFieldType::Datetime->value => [CollectionFieldType::Datetime],
            CollectionFieldType::Email->value => [CollectionFieldType::Email],
            CollectionFieldType::Url->value => [CollectionFieldType::Url],
            CollectionFieldType::Json->value => [CollectionFieldType::Json],
            CollectionFieldType::Relation->value => [CollectionFieldType::Relation],
        ];
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
     * Fields are matched by name. A name change appears as a drop + add.
     *
     * @throws InvalidArgumentException
     */
    public static function buildPlan(array $before, array $after, bool $isAuthCollection = false): self
    {
        $plan = new self;

        $beforeByName = collect($before)->keyBy('name');
        $afterByName = collect($after)->keyBy('name');

        foreach ($afterByName as $name => $field) {
            if (! $beforeByName->has($name)) {
                $plan->adds[] = $field;
            } elseif ($field !== $beforeByName[$name]) {
                $plan->modifies[] = [$beforeByName[$name], $field];
            }
        }

        foreach ($beforeByName as $name => $field) {
            if (! $afterByName->has($name)) {
                $plan->drops[] = $field;
            }
        }

        $plan->validatePlan($plan, $isAuthCollection);

        return $plan;
    }

    /**
     * @throws InvalidArgumentException
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
            $this->assertCompatibleChange($old, $new);
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
    private function assertCompatibleChange(array $old, array $new): void
    {
        $from = CollectionFieldType::tryFrom($old['type']);
        $to = CollectionFieldType::tryFrom($new['type']);

        if ($from === $to) {
            return;
        }

        $allowed = self::compatibleTypeChanges()[$from->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new \LogicException(
                "Incompatible type change on field '{$new['name']}': cannot change '{$from->value}' to '{$to->value}'."
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
