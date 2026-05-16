<?php

namespace Veloquent\Core\Domain\Collections\Validators;

use Illuminate\Support\Facades\DB;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Support\PivotTableName;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;
use Veloquent\Core\Domain\Collections\ValueObjects\ValidationResult;

class CollectionValidator
{
    public function validateCreate(array $fields, bool $isAuthCollection): ValidationResult
    {
        $errors = [];

        $errors = array_merge($errors, $this->checkRelationTargetsExist($fields));
        $errors = array_merge($errors, $this->checkPivotFieldNames($fields));

        return new ValidationResult($errors);
    }

    public function validateUpdate(Collection $existing, array $newFields, bool $force = false): ValidationResult
    {
        $errors = [];

        $isAuthCollection = $existing->type === CollectionType::Auth;
        $storedFields = collect($existing->fields ?? [])
            ->map(fn (mixed $field): array => is_array($field) ? $field : (array) $field)
            ->values()
            ->all();

        $errors = array_merge($errors, $this->checkTypeChanges($newFields, $storedFields));
        $errors = array_merge($errors, $this->checkDropConstraints($newFields, $storedFields, $isAuthCollection));
        $errors = array_merge($errors, $this->checkAuthFieldIntegrity($newFields, $isAuthCollection));
        $errors = array_merge($errors, $this->checkRelationTargetsExist($newFields));
        $errors = array_merge($errors, $this->checkRetargetedRelationsHaveNoData($existing, $newFields, $force));
        $errors = array_merge($errors, $this->checkPivotFieldNames($newFields));

        return new ValidationResult($errors);
    }

    private function checkTypeChanges(array $incomingFields, array $storedFields, string $pathPrefix = 'fields'): array
    {
        $errors = [];

        $storedById = collect($storedFields)
            ->filter(fn (array $field) => isset($field['id']) && is_string($field['id']) && $field['id'] !== '')
            ->keyBy('id');

        foreach ($incomingFields as $index => $field) {
            $id = $field['id'] ?? null;

            if (! is_string($id) || $id === '' || ! $storedById->has($id)) {
                continue;
            }

            $stored = $storedById->get($id);
            $oldType = (string) ($stored['type'] ?? '');
            $newType = (string) ($field['type'] ?? '');

            if ($oldType !== '' && $newType !== '' && $oldType !== $newType) {
                $name = (string) ($field['name'] ?? $stored['name'] ?? 'unknown');
                $errors["{$pathPrefix}.{$index}.type"] = ["Field '{$name}' cannot change type from '{$oldType}' to '{$newType}'. To change the type, remove the field and add it again with the new type."];
            }

            $oldPivotFields = $stored['pivot_fields'] ?? [];
            $newPivotFields = $field['pivot_fields'] ?? [];

            if (! empty($oldPivotFields) || ! empty($newPivotFields)) {
                $errors = array_merge(
                    $errors,
                    $this->checkTypeChanges($newPivotFields, $oldPivotFields, "{$pathPrefix}.{$index}.pivot_fields")
                );
            }
        }

        return $errors;
    }

    private function checkDropConstraints(array $incomingFields, array $storedFields, bool $isAuthCollection): array
    {
        $errors = [];

        $incomingIds = collect($incomingFields)
            ->map(fn (array $field) => $field['id'] ?? null)
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values()
            ->all();

        foreach ($storedFields as $storedPosition => $storedField) {
            $storedId = $storedField['id'] ?? null;

            if (! is_string($storedId) || $storedId === '' || in_array($storedId, $incomingIds, true)) {
                continue;
            }

            $name = (string) ($storedField['name'] ?? 'unknown');

            if (($storedField['required'] ?? false) === true) {
                $errors["fields.{$storedPosition}"] = ["Field '{$name}' is required and cannot be removed. Set required to false before removing it."];
            }

            if ($isAuthCollection && in_array($name, SchemaChange::getAuthReservedFields(), true)) {
                $errors["fields.{$storedPosition}"] = ["Field '{$name}' is a reserved auth field and cannot be removed from an auth collection."];
            }
        }

        return $errors;
    }

    private function checkAuthFieldIntegrity(array $incomingFields, bool $isAuthCollection): array
    {
        if (! $isAuthCollection) {
            return [];
        }

        $errors = [];
        $reservedDefinitions = SchemaChange::getReservedFieldDefinitions(true);

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
                $errors["fields.{$index}.type"] = ["Field '{$name}' is a reserved auth field. type must be '{$expectedType}'."];
            }

            if ($actualNullable !== $expectedNullable) {
                $expectedNullableLabel = $expectedNullable ? 'true' : 'false';
                $errors["fields.{$index}.nullable"] = ["Field '{$name}' is a reserved auth field. nullable must be '{$expectedNullableLabel}'."];
            }
        }

        return $errors;
    }

    private function checkRelationTargetsExist(array $fields): array
    {
        $errors = [];

        foreach ($fields as $index => $field) {
            $type = CollectionFieldType::tryFrom((string) ($field['type'] ?? ''));
            if ($type !== CollectionFieldType::Relation && $type !== CollectionFieldType::RelationMany) {
                continue;
            }

            $targetCollectionId = $field['target_collection_id'] ?? null;

            if (! is_string($targetCollectionId) || $targetCollectionId === '') {
                $errors["fields.{$index}.target_collection_id"] = ['The target collection is required.'];
                continue;
            }

            $targetCollection = Collection::query()->find($targetCollectionId);
            if ($targetCollection === null) {
                $errors["fields.{$index}.target_collection_id"] = ['The selected target collection is invalid.'];
                continue;
            }

            if ($targetCollection->is_system) {
                $errors["fields.{$index}.target_collection_id"] = ['System collections cannot be used as relation targets.'];
            }
        }

        return $errors;
    }

    private function checkRetargetedRelationsHaveNoData(Collection $existing, array $newFields, bool $force): array
    {
        if ($force) {
            return [];
        }

        $errors = [];
        $storedFields = collect($existing->fields ?? [])
            ->filter(fn ($f) => ($f['type'] ?? '') === CollectionFieldType::RelationMany->value)
            ->keyBy('id');

        foreach ($newFields as $index => $field) {
            if (($field['type'] ?? '') !== CollectionFieldType::RelationMany->value) {
                continue;
            }

            $id = $field['id'] ?? null;
            if (! $id || ! $storedFields->has($id)) {
                continue;
            }

            $storedField = $storedFields->get($id);
            $oldTarget = $storedField['target_collection_id'] ?? null;
            $newTarget = $field['target_collection_id'] ?? null;

            if ($oldTarget === $newTarget) {
                continue;
            }

            // Target changed. Check if pivot table has data.
            $pivotTable = PivotTableName::for($existing->name, $storedField['name']);
            
            if (DB::getSchemaBuilder()->hasTable($pivotTable) && DB::table($pivotTable)->exists()) {
                $errors["fields.{$index}.target_collection_id"] = ["Changing the target collection of a relation many field will destroy all existing data in the pivot table. Use 'force: true' to confirm this action."];
            }
        }

        return $errors;
    }

    private function checkPivotFieldNames(array $fields): array
    {
        $errors = [];
        $reserved = ['id', 'source_id', 'target_id', 'created_at', 'updated_at'];
        $nameRegex = '/^[a-zA-Z][a-zA-Z0-9_]*$/';

        foreach ($fields as $index => $field) {
            if (($field['type'] ?? '') !== CollectionFieldType::RelationMany->value) {
                continue;
            }

            $pivotFields = $field['pivot_fields'] ?? [];
            if (! is_array($pivotFields)) {
                continue;
            }

            foreach ($pivotFields as $pIndex => $pField) {
                $name = $pField['name'] ?? null;
                if (!$name) continue;

                if (in_array($name, $reserved, true)) {
                    $errors["fields.{$index}.pivot_fields.{$pIndex}.name"] = ["The pivot field name '{$name}' is reserved."];
                }

                if (!preg_match($nameRegex, $name)) {
                    $errors["fields.{$index}.pivot_fields.{$pIndex}.name"] = ["The pivot field name '{$name}' is invalid. It must start with a letter and contain only alphanumeric characters and underscores."];
                }
            }
        }

        return $errors;
    }
}
