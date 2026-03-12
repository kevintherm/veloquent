<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\SchemaManagement\Policies\SchemaPolicy;
use App\Infrastructure\Exceptions\InvalidArgumentException;

final class SchemaChangePlan
{
    /**
     * System field names that are auto-managed and should be ignored from requests.
     */
    private const SYSTEM_FIELD_NAMES = ['id', 'created_at', 'updated_at'];

    /**
     * Clean fields array by removing any system fields.
     * System fields coming from request are silently ignored.
     */
    public static function cleanFields(array $fields): array
    {
        return array_values(array_filter($fields, function ($field) {
            return ! in_array($field['name'] ?? '', self::SYSTEM_FIELD_NAMES, true);
        }));
    }

    /**
     * Get system fields with proper metadata for storage.
     */
    public static function getSystemFields(): array
    {
        return [
            [
                'name' => 'id',
                'type' => CollectionFieldType::Text,
                'nullable' => false,
                'unique' => true,
                'length' => 26,
            ],
            [
                'name' => 'created_at',
                'type' => CollectionFieldType::Datetime,
                'nullable' => false,
                'unique' => false,
            ],
            [
                'name' => 'updated_at',
                'type' => CollectionFieldType::Datetime,
                'nullable' => false,
                'unique' => false,
            ],
        ];
    }

    /**
     * Merge system fields with user fields for metadata storage.
     * System fields are always prepended.
     */
    public static function mergeWithSystemFields(array $fields): array
    {
        $cleaned = self::cleanFields($fields);

        return array_merge(self::getSystemFields(), $cleaned);
    }

    /**
     * Type changes that are always safe (no data loss, no cast ambiguity).
     *
     * @return array<string, CollectionFieldType[]>
     */
    private static function compatibleTypeChanges(): array
    {
        return [
            CollectionFieldType::Text->value => [CollectionFieldType::Text],
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
    public static function buildPlan(array $before, array $after): self
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

        $plan->validatePlan($plan);

        return $plan;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validatePlan(self $plan): void
    {
        foreach ($plan->adds as $field) {
            $this->assertValidField($field);
        }

        foreach ($plan->modifies as [$old, $new]) {
            $this->assertValidField($new);
            $this->assertCompatibleChange($old, $new);
        }

        foreach ($plan->drops as $field) {
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
}
