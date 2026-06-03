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
    public function validateForCreate(array $fields, array $indexes, bool|string|null $collectionType): void
    {
        $type = $collectionType;
        if (is_bool($collectionType)) {
            $type = $collectionType ? 'auth' : 'base';
        }
        $type ??= 'base';

        $errors = [];

        $errors = $this->mergeErrors($errors, $this->checkReservedNames($fields, $type));
        $errors = $this->mergeErrors($errors, $this->checkDuplicateNames($fields));
        $errors = $this->mergeErrors($errors, $this->checkFieldShapes($fields));
        $errors = $this->mergeErrors($errors, $this->checkIndexes($indexes, $fields, $type));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $incomingFields
     * @param  array<int, array<string, mixed>>  $storedFields
     * @param  array<int, Index|array<string, mixed>>  $incomingIndexes
     */
    public function validateForUpdate(array $incomingFields, array $storedFields, array $incomingIndexes, bool|string|null $collectionType): void
    {
        $type = $collectionType;
        if (is_bool($collectionType)) {
            $type = $collectionType ? 'auth' : 'base';
        }
        $type ??= 'base';

        $errors = [];

        $errors = $this->mergeErrors($errors, $this->checkDuplicateNames($incomingFields));
        $errors = $this->mergeErrors($errors, $this->checkFieldShapes($incomingFields));
        $errors = $this->mergeErrors($errors, $this->checkTypeChanges($incomingFields, $storedFields));
        $errors = $this->mergeErrors($errors, $this->checkDroppedReservedFields($incomingFields, $storedFields, $type));
        $errors = $this->mergeErrors($errors, $this->checkReservedFieldModifications($incomingFields, $storedFields, $type));
        $errors = $this->mergeErrors($errors, $this->checkIndexes($incomingIndexes, $incomingFields, $type));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<int, string>>
     */
    private function checkReservedNames(array $fields, string $collectionType): array
    {
        $errors = [];
        $reservedNames = SchemaChange::getAllReservedFields($collectionType);

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

    private function checkDroppedReservedFields(array $incomingFields, array $storedFields, string $collectionType): array
    {
        $reservedFields = [];
        if ($collectionType === 'auth') {
            $reservedFields = SchemaChange::getAuthReservedFields();
        } elseif ($collectionType === 'agents') {
            $reservedFields = SchemaChange::getAgentsReservedFields();
        }

        if (empty($reservedFields)) {
            return [];
        }

        $errors = [];
        $incomingNames = collect($incomingFields)->pluck('name')->all();

        foreach ($storedFields as $index => $field) {
            $name = is_array($field) ? ($field['name'] ?? null) : $field->name;

            if (in_array($name, $reservedFields, true) && !in_array($name, $incomingNames, true)) {
                $this->addError($errors, "fields.{$index}", "Reserved field '{$name}' cannot be removed.");
            }
        }

        return $errors;
    }

    private function checkReservedFieldModifications(array $incomingFields, array $storedFields, string $collectionType): array
    {
        $errors = [];
        $reservedNames = SchemaChange::getAllReservedFields($collectionType);
        
        $storedReserved = collect($storedFields)
            ->map(fn($f) => is_array($f) ? $f : (array) $f)
            ->filter(fn($f) => in_array($f['name'] ?? '', $reservedNames, true))
            ->keyBy('name');

        if ($storedReserved->isEmpty()) {
            return [];
        }

        $incomingByName = collect($incomingFields)->keyBy('name');

        foreach ($storedReserved as $name => $storedField) {
            $incomingField = $incomingByName->get($name);

            if ($incomingField) {
                $reservedDefinitions = SchemaChange::getReservedFieldDefinitions($collectionType);
                $expected = $reservedDefinitions[$name] ?? null;

                if ($expected) {
                    $diffKeys = ['type', 'unique', 'nullable', 'default'];
                    foreach ($diffKeys as $key) {
                        $storedVal = $expected[$key] ?? null;
                        $incomingVal = $incomingField[$key] ?? null;

                        if ($incomingVal === null) {
                            continue;
                        }

                        $normalize = function ($val) {
                            if ($val === 'true' || $val === '1' || $val === 1 || $val === true) {
                                return true;
                            }
                            if ($val === 'false' || $val === '0' || $val === 0 || $val === false) {
                                return false;
                            }
                            return $val;
                        };

                        if ($normalize($storedVal) !== $normalize($incomingVal)) {
                            $this->addError(
                                $errors,
                                "fields",
                                "Reserved field '{$name}' properties cannot be modified."
                            );
                            break;
                        }
                    }
                }
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
    private function checkIndexes(array $indexes, array $fields, string $collectionType): array
    {
        $errors = [];

        $fieldTypesByName = collect($fields)
            ->filter(fn (array $field): bool => isset($field['name'], $field['type']) && is_string($field['name']) && is_string($field['type']))
            ->mapWithKeys(fn (array $field): array => [$field['name'] => (string) $field['type']])
            ->all();

        foreach (SchemaChange::getReservedFieldDefinitions($collectionType) as $name => $definition) {
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
