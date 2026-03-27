<?php

namespace App\Domain\QueryCompiler\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;

class AllowedFieldsResolver
{
    /**
     * @return array<int, string>
     */
    public function resolveFromCollection(Collection $collection): array
    {
        return $this->resolveFromFieldDefinitions($collection->fields ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fieldDefinitions
     * @return array<int, string>
     */
    public function resolveFromFieldDefinitions(array $fieldDefinitions, string $prefix = '', int $depth = 0): array
    {
        $allowedFields = $depth === 0
            ? ['id', 'token', 'created_at', 'updated_at']
            : [];

        if ($depth > 2) {
            return $allowedFields;
        }

        foreach ($fieldDefinitions as $fieldDefinition) {
            $name = $fieldDefinition['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $fullPath = $prefix !== '' ? "{$prefix}.{$name}" : $name;
            $allowedFields[] = $fullPath;

            $fieldType = $fieldDefinition['type'] ?? null;
            if ($fieldType instanceof CollectionFieldType) {
                $fieldType = $fieldType->value;
            }

            if ($fieldType !== CollectionFieldType::Relation->value) {
                continue;
            }

            $allowedFields[] = "{$fullPath}.id";
            $allowedFields[] = "{$fullPath}.created_at";
            $allowedFields[] = "{$fullPath}.updated_at";

            $targetCollectionId = $fieldDefinition['target_collection_id'] ?? null;
            if (! is_string($targetCollectionId) || $targetCollectionId === '') {
                continue;
            }

            $targetCollection = Collection::query()->find($targetCollectionId);
            if ($targetCollection === null) {
                continue;
            }

            $allowedFields = array_merge(
                $allowedFields,
                $this->resolveFromFieldDefinitions($targetCollection->fields ?? [], $fullPath, $depth + 1)
            );
        }

        return array_values(array_unique($allowedFields));
    }
}
