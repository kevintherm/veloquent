<?php

namespace App\Domain\Collections\Validators;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\IndexType;
use App\Domain\Collections\ValueObjects\Index;
use App\Domain\SchemaManagement\Policies\SchemaPolicy;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use App\Infrastructure\Exceptions\InvalidArgumentException;
use Illuminate\Validation\ValidationException;

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
        $errors = $this->mergeErrors($errors, $this->checkAuthFieldIntegrity($incomingFields, $isAuthCollection));
        $errors = $this->mergeErrors($errors, $this->checkDropConstraints($incomingFields, $storedFields, $isAuthCollection));
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
        $reservedNames = SchemaChangePlan::getAllReservedFields($isAuthCollection);

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
                    "fields.{$index}.name must match /^[a-zA-Z_]+$/ and fields.{$index}.type must be one of: {$allowedTypes}."
                );
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $incomingFields
     * @param  array<int, array<string, mixed>>  $storedFields
     * @return array<string, array<int, string>>
     */
    private function checkTypeChanges(array $incomingFields, array $storedFields): array
    {
        $errors = [];

        $storedById = collect($storedFields)
            ->filter(function (array $field): bool {
                $id = $field['id'] ?? null;

                return is_string($id) && $id !== '';
            })
            ->keyBy('id');

        foreach ($incomingFields as $index => $field) {
            $id = $field['id'] ?? null;

            if (! is_string($id) || $id === '' || ! $storedById->has($id)) {
                continue;
            }

            $stored = $storedById->get($id);
            $oldType = (string) ($stored['type'] ?? '');
            $newType = (string) ($field['type'] ?? '');

            if ($oldType === '' || $newType === '' || $oldType === $newType) {
                continue;
            }

            $name = (string) ($field['name'] ?? $stored['name'] ?? 'unknown');

            $this->addError(
                $errors,
                "fields.{$index}.type",
                "Field '{$name}' cannot change type from '{$oldType}' to '{$newType}'. To change the type, remove the field and add it again with the new type."
            );
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $incomingFields
     * @return array<string, array<int, string>>
     */
    private function checkAuthFieldIntegrity(array $incomingFields, bool $isAuthCollection): array
    {
        if (! $isAuthCollection) {
            return [];
        }

        $errors = [];
        $reservedDefinitions = SchemaChangePlan::getReservedFieldDefinitions(true);

        foreach ($incomingFields as $index => $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || ! isset($reservedDefinitions[$name])) {
                continue;
            }

            $expectedType = (string) ($reservedDefinitions[$name]['type'] ?? '');
            $expectedNullable = (bool) ($reservedDefinitions[$name]['nullable'] ?? false);
            $actualType = (string) ($field['type'] ?? '');
            $actualNullable = (bool) ($field['nullable'] ?? false);

            if ($actualType !== '' && $actualType !== $expectedType) {
                $this->addError(
                    $errors,
                    "fields.{$index}.type",
                    "Field '{$name}' is a reserved auth field. type must be '{$expectedType}'."
                );
            }

            if ($actualNullable !== $expectedNullable) {
                $expectedNullableLabel = $expectedNullable ? 'true' : 'false';
                $this->addError(
                    $errors,
                    "fields.{$index}.nullable",
                    "Field '{$name}' is a reserved auth field. nullable must be '{$expectedNullableLabel}'."
                );
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $incomingFields
     * @param  array<int, array<string, mixed>>  $storedFields
     * @return array<string, array<int, string>>
     */
    private function checkDropConstraints(array $incomingFields, array $storedFields, bool $isAuthCollection): array
    {
        $errors = [];

        $incomingIds = collect($incomingFields)
            ->map(fn (array $field): mixed => $field['id'] ?? null)
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        foreach ($storedFields as $storedPosition => $storedField) {
            $storedId = $storedField['id'] ?? null;

            if (! is_string($storedId) || $storedId === '' || in_array($storedId, $incomingIds, true)) {
                continue;
            }

            $name = (string) ($storedField['name'] ?? 'unknown');

            if (($storedField['required'] ?? false) === true) {
                $this->addError(
                    $errors,
                    "fields.{$storedPosition}",
                    "Field '{$name}' is required and cannot be removed. Set required to false before removing it."
                );
            }

            if ($isAuthCollection && in_array($name, SchemaChangePlan::getAuthReservedFields(), true)) {
                $this->addError(
                    $errors,
                    "fields.{$storedPosition}",
                    "Field '{$name}' is a reserved auth field and cannot be removed from an auth collection."
                );
            }
        }

        return $errors;
    }

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

        foreach (SchemaChangePlan::getReservedFieldDefinitions($isAuthCollection) as $name => $definition) {
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

                /**
                 * @TODO: Relation indexing is not yet supported and needs implementation.
                 */
                $message = $columnType === CollectionFieldType::Relation
                    ? "Field '{$columnName}' of type 'relation' is not yet supported for indexing."
                    : "Field '{$columnName}' of type '{$columnType->value}' cannot be indexed.";

                $this->addError(
                    $errors,
                    "indexes.{$indexPosition}.columns.{$columnPosition}",
                    $message
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
