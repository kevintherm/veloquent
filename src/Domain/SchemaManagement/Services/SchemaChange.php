<?php

namespace Veloquent\Core\Domain\SchemaManagement\Services;

use Veloquent\Core\Domain\Collections\ValueObjects\Field;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;

final class SchemaChange
{
    public function __construct(
        public array $renames = [],
        public array $adds = [],
        public array $modifies = [],
        public array $drops = [],
        public array $pivotAdds = [],
        public array $pivotDrops = [],
        public array $pivotRenames = [],
        public array $pivotRetargets = [],
        public array $pivotModifies = [],
    ) {}

    public function hasChanges(): bool
    {
        return !empty($this->renames) ||
               !empty($this->adds) ||
               !empty($this->modifies) ||
               !empty($this->drops) ||
               !empty($this->pivotAdds) ||
               !empty($this->pivotDrops) ||
               !empty($this->pivotRenames) ||
               !empty($this->pivotRetargets) ||
               !empty($this->pivotModifies);
    }

    public static function getAuthReservedFields(): array
    {
        return CollectionType::Auth->reservedFields();
    }

    public static function getAgentsReservedFields(): array
    {
        return CollectionType::Agents->reservedFields();
    }

    public static function getAllReservedFields(CollectionType|string|bool|null $collectionType = null): array
    {
        return CollectionType::parse($collectionType)->reservedFields();
    }

    public static function getSystemFields(): array
    {
        return CollectionType::Base->systemFields();
    }

    public static function getAuthSystemFields(): array
    {
        return collect(CollectionType::Auth->systemFields())
            ->reject(fn(array $f) => in_array($f['name'], ['id', 'created_at', 'updated_at'], true))
            ->values()
            ->all();
    }

    public static function getAgentsSystemFields(): array
    {
        return collect(CollectionType::Agents->systemFields())
            ->reject(fn(array $f) => in_array($f['name'], ['id', 'created_at', 'updated_at'], true))
            ->values()
            ->all();
    }

    public static function mergeWithSystemFields(array $userFields, CollectionType|string|bool|null $collectionType = null): array
    {
        $type = CollectionType::parse($collectionType);
        $reservedNames = array_keys(self::getReservedFieldDefinitions($type));
        
        $normalizedUserFields = collect($userFields)
            ->filter(fn($f) => is_array($f) && isset($f['name']) && !in_array($f['name'], $reservedNames, true))
            ->map(fn($f) => self::normalizeInputField($f))
            ->values()
            ->all();

        $reservedDefinitions = self::getReservedFieldDefinitions($type);

        $merged = [
            $reservedDefinitions['id'],
            ...($type === CollectionType::Auth ? self::getAuthSystemFields() : []),
            ...($type === CollectionType::Agents ? self::getAgentsSystemFields() : []),
            ...$normalizedUserFields,
            $reservedDefinitions['created_at'],
            $reservedDefinitions['updated_at'],
        ];

        return collect($merged)->values()->map(function (array $field, int $index) {
            $field['order'] = $index;
            $field['id'] ??= self::generateFieldId();

            if (($field['type'] ?? '') === CollectionFieldType::RelationMany->value && isset($field['pivot_fields'])) {
                $field['pivot_fields'] = self::normalizePivotFields($field['pivot_fields']);
            }

            return $field;
        })->all();
    }

    public static function normalizePivotFields(array $fields): array
    {
        return collect($fields)->map(function ($pf) {
            if (is_string($pf)) {
                $pf = ['name' => $pf, 'type' => 'text'];
            }

            $pf['id'] ??= self::generateFieldId();

            return self::normalizeFieldDefinition($pf);
        })->all();
    }

    public static function getReservedFieldDefinitions(CollectionType|string|bool|null $collectionType = null): array
    {
        return collect(CollectionType::parse($collectionType)->systemFields())->keyBy('name')->all();
    }

    public static function stripForDDL(array $fields): array
    {
        return collect($fields)
            ->map(fn($f) => self::normalizeInputField($f))
            ->reject(fn($f) => in_array($f['name'], CollectionType::Base->reservedFields(), true))
            ->reject(fn($f) => ($f['type'] ?? '') === CollectionFieldType::RelationMany->value)
            ->map(function ($f) {
                unset($f['order']);
                return $f;
            })
            ->values()
            ->all();
    }

    public static function diff(array $before, array $after): self
    {
        $change = new self;
        $before = collect($before)
            ->map(fn($f) => $f instanceof Field ? $f->toArray() : $f)
            ->reject(fn($f) => in_array($f['name'] ?? null, CollectionType::Base->reservedFields(), true))
            ->all();
        $after = collect($after)
            ->map(fn($f) => $f instanceof Field ? $f->toArray() : $f)
            ->reject(fn($f) => in_array($f['name'] ?? null, CollectionType::Base->reservedFields(), true))
            ->all();

        $beforeById = collect($before)->filter(fn(array $f) => isset($f['id']) && is_string($f['id']) && $f['id'] !== '')->keyBy('id');
        $matchedBeforeIds = [];

        foreach (array_values($after) as $field) {
            $fieldId = $field['id'] ?? null;

            if (!$fieldId || !$beforeById->has($fieldId)) {
                $field['id'] ??= self::generateFieldId();
                if (($field['type'] ?? '') === CollectionFieldType::RelationMany->value) {
                    $change->pivotAdds[] = $field;
                } else {
                    $change->adds[] = $field;
                }
                continue;
            }

            $matchedBeforeIds[$fieldId] = true;
            $oldField = $beforeById->get($fieldId);
            $isPivot = ($field['type'] ?? '') === CollectionFieldType::RelationMany->value;
            $oldIsPivot = ($oldField['type'] ?? '') === CollectionFieldType::RelationMany->value;

            // Handle Type Changes (Non-Pivot <-> Pivot)
            if (!$oldIsPivot && $isPivot) {
                $change->pivotAdds[] = $field;
                $change->drops[] = $oldField;
                continue;
            } 
            
            if ($oldIsPivot && !$isPivot) {
                $change->pivotDrops[] = $oldField;
                $change->adds[] = $field;
                continue;
            }

            // Handle Renames
            if (($oldField['name'] ?? null) !== ($field['name'] ?? null)) {
                if ($isPivot) {
                    $change->pivotRenames[] = [
                        'from' => $oldField['name'],
                        'to' => $field['name'],
                        'target' => $field['target_collection_id'] ?? $oldField['target_collection_id'],
                    ];
                } else {
                    $change->renames[] = [$oldField['name'], $field['name']];
                }
            }

            // Handle Retargets
            if ($isPivot && ($oldField['target_collection_id'] ?? null) !== ($field['target_collection_id'] ?? null)) {
                $change->pivotRetargets[] = [
                    'old' => $oldField,
                    'new' => $field
                ];
            }

            // Handle pivot field modifications (extra columns)
            if ($isPivot && $oldIsPivot) {
                $oldPivotFields = $oldField['pivot_fields'] ?? [];
                $newPivotFields = $field['pivot_fields'] ?? [];

                if ($oldPivotFields !== $newPivotFields) {
                    $columnChanges = self::diffColumns($oldPivotFields, $newPivotFields);

                    if ($columnChanges->hasChanges()) {
                        $change->pivotModifies[] = [
                            'old' => $oldField,
                            'new' => $field,
                            'changes' => $columnChanges,
                        ];
                    }
                }
            }

            // Handle general modifies (non-pivots)
            if (!$isPivot) {
                $comparableOld = $oldField;
                $comparableNew = $field;
                unset($comparableOld['order'], $comparableNew['order']);
                if ($comparableOld !== $comparableNew) {
                    $change->modifies[] = [$oldField, $field];
                }
            }
        }

        foreach ($beforeById as $id => $field) {
            if (isset($matchedBeforeIds[$id])) continue;
            if (($field['type'] ?? '') === CollectionFieldType::RelationMany->value) {
                $change->pivotDrops[] = $field;
            } else {
                $change->drops[] = $field;
            }
        }

        return $change;
    }

    public static function generateFieldId(): string
    {
        return bin2hex(random_bytes(4));
    }

    public static function diffColumns(array $before, array $after): self
    {
        $change = new self;
        $beforeById = collect($before)->filter(fn($f) => isset($f['id']) && is_string($f['id']) && $f['id'] !== '')->keyBy('id');
        $matchedBeforeIds = [];

        foreach (array_values($after) as $field) {
            $fieldId = $field['id'] ?? null;

            if (!$fieldId || !$beforeById->has($fieldId)) {
                $change->adds[] = $field;
                continue;
            }

            $matchedBeforeIds[$fieldId] = true;
            $oldField = $beforeById->get($fieldId);

            // Handle Renames
            if (($oldField['name'] ?? null) !== ($field['name'] ?? null)) {
                $change->renames[] = [$oldField['name'], $field['name']];
            }

            // Handle general modifies
            $comparableOld = $oldField;
            $comparableNew = $field;
            unset($comparableOld['order'], $comparableNew['order']);
            if ($comparableOld !== $comparableNew) {
                $change->modifies[] = [$oldField, $field];
            }
        }

        foreach ($beforeById as $id => $field) {
            if (isset($matchedBeforeIds[$id])) continue;
            $change->drops[] = $field;
        }

        return $change;
    }


    private static function normalizeFieldDefinition(array $field): array
    {
        $type = CollectionFieldType::from($field['type']);
        return collect([...$type->defaultShape(), ...$field, 'type' => $type->value])
            ->only($type->allowedProperties())
            ->all();
    }

    private static function normalizeInputField(array|Field $field): array
    {
        return $field instanceof Field ? $field->toArray() : self::normalizeFieldDefinition($field);
    }
}
