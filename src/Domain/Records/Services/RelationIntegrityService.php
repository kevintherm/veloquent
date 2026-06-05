<?php

namespace Veloquent\Core\Domain\Records\Services;

use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\ValueObjects\Field;
use Veloquent\Core\Domain\Records\Models\Record;
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

            if ($fieldType !== CollectionFieldType::Relation->value && $fieldType !== CollectionFieldType::RelationMany->value) {
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

            /** @var Collection|null $targetCollection */
            $targetCollection = Collection::query()->find($targetCollectionId);

            if ($targetCollection === null) {
                $errors[$fieldName] = ["Target collection for field '{$fieldName}' does not exist."];

                continue;
            }

            $tableName = $targetCollection->getPhysicalTableName();

            if ($fieldType === CollectionFieldType::RelationMany->value) {
                if (! is_array($value)) {
                    $errors[$fieldName] = ["The value for '{$fieldName}' must be an array."];
                    continue;
                }

                $ids = [];
                foreach ($value as $item) {
                    $id = is_array($item) ? ($item['id'] ?? null) : $item;
                    if (is_string($id) && $id !== '') {
                        $ids[] = $id;
                    }
                }

                if (empty($ids)) {
                    continue;
                }

                $foundCount = DB::table($tableName)->whereIn('id', $ids)->count();
                if ($foundCount !== count(array_unique($ids))) {
                    $errors[$fieldName] = ["One or more of the selected related records do not exist in collection '{$targetCollection->name}'."];
                }
            } else {
                $exists = DB::table($tableName)->where('id', $value)->exists();
                if (! $exists) {
                    $errors[$fieldName] = ["The referenced record '{$value}' does not exist in collection '{$targetCollection->name}'."];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate that setting relation fields does not introduce any circular references (e.g. A -> B -> C -> A).
     *
     * @param  array<string, mixed>  $data
     */
    public function validateNoCircularReferences(Collection $collection, ?string $recordId, array $data): void
    {
        $idToCheck = $recordId ?? ($data['id'] ?? null);

        if (empty($idToCheck)) {
            return;
        }

        $startingNodes = [];

        foreach ($collection->fields ?? [] as $field) {
            $fieldName = $field['name'] ?? null;
            $fieldType = $field['type'] ?? null;

            if ($fieldType !== CollectionFieldType::Relation->value) {
                continue;
            }

            if (! array_key_exists($fieldName, $data) || empty($data[$fieldName])) {
                continue;
            }

            $targetCollectionId = $field['target_collection_id'] ?? null;
            if (empty($targetCollectionId)) {
                continue;
            }

            $targetId = $data[$fieldName];

            if ($targetId === $idToCheck && $targetCollectionId === $collection->id) {
                throw ValidationException::withMessages([
                    $fieldName => ["Circular reference detected: '{$fieldName}' cannot reference the record itself."],
                ]);
            }

            $startingNodes[] = [
                'collection_id' => $targetCollectionId,
                'record_id' => $targetId,
                'field_name' => $fieldName,
            ];
        }

        if (empty($startingNodes)) {
            return;
        }

        $this->detectCircularRelationsBfs($collection, $idToCheck, $startingNodes);
    }

    /**
     * @param  array<int, array{collection_id: string, record_id: string, field_name: string}>  $startingNodes
     */
    private function detectCircularRelationsBfs(Collection $collection, string $idToCheck, array $startingNodes): void
    {
        $collectionsById = collect([$collection->id => $collection]);
        $visited = [];
        $visited[$collection->id.':'.$idToCheck] = true;

        $queue = $startingNodes;

        while (! empty($queue)) {
            $currentNode = array_shift($queue);
            $cId = $currentNode['collection_id'];
            $rId = $currentNode['record_id'];
            $originFieldName = $currentNode['field_name'] ?? null;

            if ($cId === $collection->id && $rId === $idToCheck) {
                throw ValidationException::withMessages([
                    $originFieldName => ['Circular reference detected: following this relation creates an infinite loop.'],
                ]);
            }

            $visitedKey = $cId.':'.$rId;
            if (isset($visited[$visitedKey])) {
                continue;
            }
            $visited[$visitedKey] = true;

            if (! $collectionsById->has($cId)) {
                $c = Collection::query()->find($cId);
                if ($c !== null) {
                    $collectionsById->put($cId, $c);
                }
            }

            $currentCollection = $collectionsById->get($cId);
            if ($currentCollection === null) {
                continue;
            }

            $relationFields = [];
            foreach ($currentCollection->fields ?? [] as $field) {
                if (($field['type'] ?? null) === CollectionFieldType::Relation->value && ! empty($field['target_collection_id'])) {
                    $relationFields[] = $field;
                }
            }

            if (empty($relationFields)) {
                continue;
            }

            $tableName = $currentCollection->getPhysicalTableName();
            $record = DB::table($tableName)->where('id', $rId)->first();

            if (! $record) {
                continue;
            }

            foreach ($relationFields as $rField) {
                $fName = $rField['name'];
                $targetCId = $rField['target_collection_id'];

                $val = $record->$fName ?? null;
                if (! empty($val)) {
                    $queue[] = [
                        'collection_id' => $targetCId,
                        'record_id' => $val,
                        'field_name' => $originFieldName,
                    ];
                }
            }
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

            if (($referencingField['type'] ?? '') === CollectionFieldType::RelationMany->value) {
                continue;
            }

            $referencingRecordExists = DB::table($tableName)->where($fieldName, $recordId)->exists();

            if (! $referencingRecordExists) {
                continue;
            }

            if (! $cascadeOnDelete) {
                throw new InvalidArgumentException("Cannot delete this record because it is referenced by collection '{$referencingCollection->name}' (field '{$fieldName}').");
            }

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
    public function findReferencingFields(string $targetCollectionId): array
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

                        if ($fieldType !== CollectionFieldType::Relation->value && $fieldType !== CollectionFieldType::RelationMany->value) {
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
