<?php

namespace App\Domain\Collections\Requests;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Enums\IndexType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Index;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('collections', 'name')->ignore($this->route('collection')->id, 'id'),
                'regex:/^[a-zA-Z_]+$/',
            ],
            'type' => ['sometimes', new Enum(CollectionType::class)],
            'description' => 'nullable|string',
            'api_rules' => 'nullable|array',
            'api_rules.list' => 'nullable|string',
            'api_rules.view' => 'nullable|string',
            'api_rules.create' => 'nullable|string',
            'api_rules.update' => 'nullable|string',
            'api_rules.delete' => 'nullable|string',

            'fields' => 'sometimes|array|min:1',
            'fields.*' => ['required', 'array'],
            'fields.*.id' => 'sometimes|string',
            'fields.*.name' => 'required|string|regex:/^[a-zA-Z_]+$/',
            'fields.*.type' => ['required', new Enum(CollectionFieldType::class)],
            'fields.*.nullable' => 'sometimes|boolean',
            'fields.*.unique' => 'sometimes|boolean',
            'fields.*.default' => 'sometimes',

            'indexes' => 'sometimes|array',
            'indexes.*' => ['required', 'array'],
            'indexes.*.name' => ['prohibited'],
            'indexes.*.columns' => ['required', 'array', 'min:1'],
            'indexes.*.columns.*' => ['required', 'string', 'regex:/^[a-zA-Z_]+$/'],
            'indexes.*.type' => ['required', new Enum(IndexType::class)],
        ];

        foreach ($this->input('fields', []) as $index => $field) {
            $type = CollectionFieldType::tryFrom($field['type'] ?? '');

            if ($type === null) {
                continue;
            }

            foreach ($type->typeValidationRules("fields.{$index}") as $key => $value) {
                $rules[$key] = $value;
            }
        }

        return $rules;
    }

    public function getFields(): array
    {
        $fields = $this->validated()['fields'] ?? [];

        return collect($fields)
            ->values()
            ->map(function (array $field, int $index): array {
                $type = CollectionFieldType::from($field['type']);

                return collect([
                    ...$type->defaultShape(),
                    ...$field,
                    'type' => $type->value,
                    'order' => $index,
                ])->only($type->allowedProperties())->all();
            })
            ->values()
            ->all();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Collection $collection */
            $collection = $this->route('collection');
            $isAuthCollection = $collection->type === CollectionType::Auth;
            $reservedDefinitions = SchemaChangePlan::getReservedFieldDefinitions($isAuthCollection);

            $fields = $this->has('fields')
                ? $this->input('fields', [])
                : collect($collection->fields)
                    ->map(function (mixed $field): array {
                        if (is_array($field)) {
                            return $field;
                        }

                        return method_exists($field, 'toArray') ? $field->toArray() : (array) $field;
                    })
                    ->all();

            $fieldsByName = collect($fields)
                ->filter(fn (mixed $field) => is_array($field) && isset($field['name']))
                ->keyBy('name')
                ->all();

            if ($this->has('fields')) {
                foreach (array_keys($reservedDefinitions) as $reservedName) {
                    if (! array_key_exists($reservedName, $fieldsByName)) {
                        $validator->errors()->add('fields', "Reserved field '{$reservedName}' must be present.");
                    }
                }
            }

            $seenNames = [];
            $fieldTypesByName = [];

            foreach ($reservedDefinitions as $reservedName => $definition) {
                $fieldTypesByName[$reservedName] = CollectionFieldType::from($definition['type']);
            }

            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldName = $field['name'] ?? null;

                if (is_string($fieldName)) {
                    if (isset($seenNames[$fieldName])) {
                        $validator->errors()->add("fields.{$index}.name", "Duplicate field name '{$fieldName}'.");
                    }

                    $seenNames[$fieldName] = true;
                }

                $fieldType = CollectionFieldType::tryFrom($field['type'] ?? '');
                if ($fieldType !== null) {
                    if (is_string($fieldName)) {
                        $fieldTypesByName[$fieldName] = $fieldType;
                    }

                    if ($fieldType === CollectionFieldType::Relation) {
                        $this->validateRelationFieldDefinition($validator, $index, $field);
                    }
                }

                $existingField = null;
                $fieldId = $field['id'] ?? null;

                if (is_string($fieldId) && $fieldId !== '') {
                    $existingField = collect($collection->fields)
                        ->first(fn (mixed $existing): bool => (string) ($existing['id'] ?? '') === $fieldId);
                }

                if ($existingField !== null) {
                    $existingFieldArray = is_array($existingField)
                        ? $existingField
                        : (method_exists($existingField, 'toArray') ? $existingField->toArray() : (array) $existingField);

                    $originalType = (string) ($existingFieldArray['type'] ?? '');
                    $incomingType = (string) ($field['type'] ?? '');

                    if ($originalType !== '' && $incomingType !== '' && $originalType !== $incomingType) {
                        $validator->errors()->add("fields.{$index}.type", 'Existing field type cannot be changed.');
                    }
                }

                if (! is_string($fieldName) || ! array_key_exists($fieldName, $reservedDefinitions)) {
                    continue;
                }

                $type = CollectionFieldType::tryFrom($field['type'] ?? '');
                if ($type === null) {
                    continue;
                }

                $normalizedIncoming = collect([
                    ...$type->defaultShape(),
                    ...$field,
                    'type' => $type->value,
                ])->only($type->allowedProperties())->all();

                $normalizedCanonical = $reservedDefinitions[$fieldName];

                unset(
                    $normalizedIncoming['order'], $normalizedCanonical['order'],
                    $normalizedIncoming['id'], $normalizedCanonical['id']
                );

                if ($normalizedIncoming !== $normalizedCanonical) {
                    $validator->errors()->add(
                        "fields.{$index}",
                        "Reserved field '{$fieldName}' cannot be modified except for order."
                    );
                }
            }

            $seenIndexSignatures = [];

            foreach ($this->input('indexes', []) as $indexPosition => $indexData) {
                if (! is_array($indexData)) {
                    continue;
                }

                $unknownProperties = array_diff(array_keys($indexData), ['columns', 'type']);
                if ($unknownProperties !== []) {
                    $validator->errors()->add(
                        "indexes.{$indexPosition}",
                        'Unknown properties for index definition: '.implode(', ', $unknownProperties)
                    );
                }

                $columns = $indexData['columns'] ?? [];
                $indexType = IndexType::tryFrom((string) ($indexData['type'] ?? ''));

                if (! is_array($columns) || $indexType === null) {
                    continue;
                }

                $normalizedColumns = array_values(array_map(fn (mixed $column): string => (string) $column, $columns));

                if (count($normalizedColumns) !== count(array_unique($normalizedColumns))) {
                    $validator->errors()->add("indexes.{$indexPosition}.columns", 'Index columns must not contain duplicates.');
                }

                foreach ($normalizedColumns as $columnPosition => $columnName) {
                    if (! array_key_exists($columnName, $fieldTypesByName)) {
                        $validator->errors()->add(
                            "indexes.{$indexPosition}.columns.{$columnPosition}",
                            "Unknown index column '{$columnName}'."
                        );

                        continue;
                    }

                    if (! $fieldTypesByName[$columnName]->isIndexable()) {
                        $validator->errors()->add(
                            "indexes.{$indexPosition}.columns.{$columnPosition}",
                            "Field '{$columnName}' of type '{$fieldTypesByName[$columnName]->value}' cannot be indexed."
                        );
                    }
                }

                $signature = implode('|', [...$normalizedColumns, $indexType->value]);

                if (isset($seenIndexSignatures[$signature])) {
                    $validator->errors()->add(
                        "indexes.{$indexPosition}",
                        'Duplicate index definition detected for the same columns and type.'
                    );
                }

                $seenIndexSignatures[$signature] = true;
            }

        });
    }

    private function validateRelationFieldDefinition(Validator $validator, int $index, array $field): void
    {
        $targetCollectionId = $field['target_collection_id'] ?? null;
        $maxSelect = $field['max_select'] ?? null;

        if (! is_string($targetCollectionId) || $targetCollectionId === '') {
            $validator->errors()->add("fields.{$index}.target_collection_id", 'The target collection is required.');

            return;
        }

        $targetCollection = Collection::query()->find($targetCollectionId);
        if ($targetCollection === null) {
            $validator->errors()->add("fields.{$index}.target_collection_id", 'The selected target collection is invalid.');

            return;
        }

        if ($targetCollection->is_system) {
            $validator->errors()->add("fields.{$index}.target_collection_id", 'System collections cannot be used as relation targets.');
        }

        if (! is_numeric($maxSelect) || (string) (int) $maxSelect !== trim((string) $maxSelect)) {
            $validator->errors()->add("fields.{$index}.max_select", 'The max_select field must be an integer.');

            return;
        }

        $maxSelect = (int) $maxSelect;

        if ($maxSelect !== 1 && $maxSelect < 2) {
            $validator->errors()->add("fields.{$index}.max_select", 'The max_select field must be 1 or greater than 1 for multiple relations.');
        }
    }

    public function getIndexes(): array
    {
        $indexes = $this->validated()['indexes'] ?? [];

        return collect($indexes)
            ->values()
            ->map(fn (array $index): array => Index::fromArray($index)->toArray())
            ->all();
    }
}
