<?php

namespace Veloquent\Core\Domain\Collections\Validators;

use Illuminate\Validation\ValidationException;
use Veloquent\Core\Domain\Collections\Enums\IndexType;
use Veloquent\Core\Domain\Collections\ValueObjects\Index;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\SchemaManagement\Policies\SchemaPolicy;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;
use Veloquent\Core\Support\Exceptions\InvalidArgumentException;

class CollectionFieldValidator
{
    public function __construct(
        private readonly SchemaPolicy $schemaPolicy,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, Index|array<string, mixed>>  $indexes
     */
    public function validateForCreate(array $fields, array $indexes, bool $isAuthCollection): void
    {
        $errors = [];

        $errors = $this->mergeErrors($errors, $this->checkReservedNames($fields, $isAuthCollection));
        $errors = $this->mergeErrors($errors, $this->checkDuplicateNames($fields));
        $errors = $this->mergeErrors($errors, $this->checkFieldShapes($fields));
        $errors = $this->mergeErrors($errors, $this->checkIndexes($indexes, $fields, $isAuthCollection));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $incomingFields
     * @param  array<int, array<string, mixed>>  $storedFields
     * @param  array<int, Index|array<string, mixed>>  $incomingIndexes
     */
    public function validateForUpdate(array $incomingFields, array $storedFields, array $incomingIndexes, bool $isAuthCollection): void
    {
        $errors = [];

        $errors = $this->mergeErrors($errors, $this->checkDuplicateNames($incomingFields));
        $errors = $this->mergeErrors($errors, $this->checkFieldShapes($incomingFields));
        $errors = $this->mergeErrors($errors, $this->checkTypeChanges($incomingFields, $storedFields));
        $errors = $this->mergeErrors($errors, $this->checkDroppedAuthFields($incomingFields, $storedFields, $isAuthCollection));
        $errors = $this->mergeErrors($errors, $this->checkIndexes($incomingIndexes, $incomingFields, $isAuthCollection));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<int, string>>
     */
    private function checkReservedNames(array $fields, bool $isAuthCollection): array
    {
        $errors = [];
        $reservedNames = SchemaChange::getAllReservedFields($isAuthCollection);

        foreach ($fields as $index => $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || ! in_array($name, $reservedNames, true)) {
                continue;
            }

            $this->addError(
                $errors,
                "fields.{$index}.name",
                "Field name '{$name}' is reserved. Use a different name. Reserved names: ".implode(', ', $reservedNames).'.'
            );
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<int, string>>
     */
    private function checkDuplicateNames(array $fields): array
    {
        $errors = [];
        $seen = [];

        foreach ($fields as $index => $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            if (isset($seen[$name])) {
                $this->addError(
                    $errors,
                    "fields.{$index}.name",
                    "Field name '{$name}' appears more than once in the request. Each field name must be unique."
                );
            }

            $seen[$name] = true;
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<int, string>>
     */
    private function checkFieldShapes(array $fields): array
    {
        $errors = [];
        $allowedTypes = collect(CollectionFieldType::cases())
            ->map(fn (CollectionFieldType $type): string => $type->value)
            ->implode(', ');

        foreach ($fields as $index => $field) {
            $type = CollectionFieldType::tryFrom((string) ($field['type'] ?? ''));

            if ($type === null) {
                $this->addError(
                    $errors,
                    "fields.{$index}.type",
                    "fields.{$index}.type must be one of: {$allowedTypes}."
                );

                continue;
            }

            $normalizedField = [
                ...$type->defaultShape(),
                ...$field,
                'type' => $type->value,
            ];

            try {
                $this->schemaPolicy->assertValidColumnDefinition($normalizedField);
            } catch (InvalidArgumentException $e) {
                $this->addError(
                    $errors,
                    "fields.{$index}.name",
                    "fields.{$index}.name must match /^[a-zA-Z][a-zA-Z0-9_]*$/ and fields.{$index}.type must be one of: {$allowedTypes}."
                );
            }

            $typeRules = $type->typeValidationRules("fields.{$index}", true); // Always skip relation exists here, domain validator handles it
            if ($typeRules !== []) {
                $validator = \Illuminate\Support\Facades\Validator::make(
                    ['fields' => [$index => $field]],
                    $typeRules
                );

                if ($validator->fails()) {
                    $errors = $this->mergeErrors($errors, $validator->errors()->toArray());
                }
            }
        }

        return $errors;
    }

    private function checkTypeChanges(array $incomingFields, array $storedFields, string $pathPrefix = 'fields'): array
    {
        $errors = [];
        $storedById = collect($storedFields)->keyBy('id');

        foreach ($incomingFields as $index => $field) {
            $id = $field['id'] ?? null;
            if (!$id || !$storedById->has($id)) {
                continue;
            }

            $oldField = $storedById->get($id);
            $oldType = is_array($oldField) ? ($oldField['type'] ?? null) : $oldField->type;
            $newType = $field['type'] ?? null;

            if ($oldType !== $newType) {
                $this->addError($errors, "{$pathPrefix}.{$index}.type", 'Field type cannot be changed.');
            }

            $oldPivotFields = is_array($oldField) ? ($oldField['pivot_fields'] ?? []) : ($oldField->pivot_fields ?? []);
            $newPivotFields = $field['pivot_fields'] ?? [];

            if (! empty($oldPivotFields) || ! empty($newPivotFields)) {
                $errors = $this->mergeErrors(
                    $errors,
                    $this->checkTypeChanges($newPivotFields, $oldPivotFields, "{$pathPrefix}.{$index}.pivot_fields")
                );
            }
        }

        return $errors;
    }

    private function checkDroppedAuthFields(array $incomingFields, array $storedFields, bool $isAuthCollection): array
    {
        if (!$isAuthCollection) {
            return [];
        }

        $errors = [];
        $incomingNames = collect($incomingFields)->pluck('name')->all();
        $requiredAuthFields = SchemaChange::getAuthReservedFields();

        foreach ($storedFields as $index => $field) {
            $name = is_array($field) ? ($field['name'] ?? null) : $field->name;

            if (in_array($name, $requiredAuthFields, true) && !in_array($name, $incomingNames, true)) {
                $this->addError($errors, "fields.{$index}", "Authentication field '{$name}' cannot be removed.");
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $incomingFields
     * @param  array<int, array<string, mixed>>  $storedFields
     * @return array<string, array<int, string>>
     */

    /**
     * @param  array<int, Index|array<string, mixed>>  $indexes
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<int, string>>
     */
    private function checkIndexes(array $indexes, array $fields, bool $isAuthCollection): array
    {
        $errors = [];

        $fieldTypesByName = collect($fields)
            ->filter(fn (array $field): bool => isset($field['name'], $field['type']) && is_string($field['name']) && is_string($field['type']))
            ->mapWithKeys(fn (array $field): array => [$field['name'] => (string) $field['type']])
            ->all();

        foreach (SchemaChange::getReservedFieldDefinitions($isAuthCollection) as $name => $definition) {
            $fieldTypesByName[$name] ??= (string) $definition['type'];
        }

        $seenSignatures = [];

        foreach ($indexes as $indexPosition => $indexInput) {
            $index = $indexInput instanceof Index
                ? $indexInput
                : Index::fromArray((array) $indexInput);

            $indexType = IndexType::tryFrom($index->type);
            if ($indexType === null) {
                continue;
            }

            $normalizedColumns = array_values($index->columns);
            $sortedColumns = $normalizedColumns;
            sort($sortedColumns);
            $signature = implode('|', [...$sortedColumns, $indexType->value]);

            if (isset($seenSignatures[$signature])) {
                $columnsLabel = implode(', ', $sortedColumns);
                $this->addError(
                    $errors,
                    "indexes.{$indexPosition}",
                    "Duplicate index: a {$indexType->value} index covering [{$columnsLabel}] already exists in this request."
                );
            }

            $seenSignatures[$signature] = true;

            foreach ($normalizedColumns as $columnPosition => $columnName) {
                if (! array_key_exists($columnName, $fieldTypesByName)) {
                    $this->addError(
                        $errors,
                        "indexes.{$indexPosition}.columns.{$columnPosition}",
                        "Index column '{$columnName}' does not match any field in this collection. Define the field first."
                    );

                    continue;
                }

                $columnType = CollectionFieldType::tryFrom((string) $fieldTypesByName[$columnName]);
                if ($columnType === null || $columnType->isIndexable()) {
                    continue;
                }

                $this->addError(
                    $errors,
                    "indexes.{$indexPosition}.columns.{$columnPosition}",
                    "Field '{$columnName}' of type '{$columnType->value}' cannot be indexed."
                );
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function addError(array &$errors, string $path, string $message): void
    {
        $errors[$path] ??= [];
        $errors[$path][] = $message;
    }

    /**
     * @param  array<string, array<int, string>>  $left
     * @param  array<string, array<int, string>>  $right
     * @return array<string, array<int, string>>
     */
    private function mergeErrors(array $left, array $right): array
    {
        foreach ($right as $path => $messages) {
            $left[$path] ??= [];
            $left[$path] = [...$left[$path], ...$messages];
        }

        return $left;
    }
}
