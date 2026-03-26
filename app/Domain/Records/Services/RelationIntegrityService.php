<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Field;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RelationIntegrityService
{
    /**
     * Validate that all relation field values reference existing records in their target collections.
     *
     * @param  array<int, Field|array>  $fields
     * @param  array<string, mixed>  $data
     */
    public function validateRelationIds(array $fields, array $data): void
    {
        $errors = [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? null;
            $fieldType = $field['type'] ?? null;

            if ($fieldType !== CollectionFieldType::Relation->value) {
                continue;
            }

            $value = $data[$fieldName] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $targetCollectionId = $field['target_collection_id'] ?? null;

            if (! is_string($targetCollectionId) || $targetCollectionId === '') {
                continue;
            }

            $targetCollection = Collection::query()->find($targetCollectionId);

            if ($targetCollection === null) {
                $errors[$fieldName] = ["Target collection for field '{$fieldName}' does not exist."];

                continue;
            }

            $tableName = $targetCollection->getPhysicalTableName();
            $exists = DB::table($tableName)->where('id', $value)->exists();

            if (! $exists) {
                $errors[$fieldName] = ["The referenced record '{$value}' does not exist in collection '{$targetCollection->name}'."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Handle record deletion with referential integrity checks.
     * If cascade_on_delete is true, delete referencing records.
     * If cascade_on_delete is false, block deletion.
     */
    public function handleRecordDeletion(Collection $collection, string $recordId): void
    {
        $referencingFields = $this->findReferencingFields($collection->id);

        foreach ($referencingFields as $ref) {
            /** @var Collection $referencingCollection */
            $referencingCollection = $ref['collection'];
            /** @var array $referencingField */
            $referencingField = $ref['field'];

            $fieldName = $referencingField['name'];
            $cascadeOnDelete = (bool) ($referencingField['cascade_on_delete'] ?? false);

            $tableName = $referencingCollection->getPhysicalTableName();
            $referencingRecordExists = DB::table($tableName)->where($fieldName, $recordId)->exists();

            if (! $referencingRecordExists) {
                continue;
            }

            if (! $cascadeOnDelete) {
                throw new InvalidArgumentException("Cannot delete this record because it is referenced by collection '{$referencingCollection->name}' (field '{$fieldName}').");
            }

            // @TODO: This should be checked recursively to ensure no circular dependencies (infinite recursion prevention)
            $referencingRecordIds = DB::table($tableName)
                ->where($fieldName, $recordId)
                ->pluck('id')
                ->all();

            foreach ($referencingRecordIds as $referencingRecordId) {
                Record::of($referencingCollection)->find($referencingRecordId)?->delete();
            }
        }
    }

    /**
     * Assert that a collection can be deleted (is not referenced by other collections).
     */
    public function assertCollectionCanBeDeleted(Collection $collection): void
    {
        $referencingFields = $this->findReferencingFields($collection->id);

        if ($referencingFields === []) {
            return;
        }

        $referencingNames = collect($referencingFields)
            ->map(fn (array $ref): string => "'{$ref['collection']->name}.{$ref['field']['name']}'")
            ->implode(', ');

        throw ValidationException::withMessages([
            'collection' => ["Cannot delete collection '{$collection->name}' because it is referenced by: {$referencingNames}."],
        ]);
    }

    /**
     * Find all collections and fields that reference the given collection ID.
     *
     * @return array<int, array{collection: Collection, field: array}>
     */
    private function findReferencingFields(string $targetCollectionId): array
    {
        $results = [];

        // Use chunking to avoid loading all collections into memory at once
        Collection::query()
            ->whereNotNull('fields')
            ->chunk(500, function ($collections) use ($targetCollectionId, &$results) {
                foreach ($collections as $collection) {
                    foreach ($collection->fields ?? [] as $field) {
                        $fieldType = $field['type'] ?? null;
                        $fieldTarget = $field['target_collection_id'] ?? null;

                        if ($fieldType !== CollectionFieldType::Relation->value) {
                            continue;
                        }

                        if ($fieldTarget !== $targetCollectionId) {
                            continue;
                        }

                        $results[] = [
                            'collection' => $collection,
                            'field' => is_array($field) ? $field : (method_exists($field, 'toArray') ? $field->toArray() : (array) $field),
                        ];
                    }
                }
            });

        return $results;
    }
}
