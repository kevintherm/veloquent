<?php

namespace App\Domain\Collections\Requests;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Enums\IndexType;
use App\Domain\Collections\ValueObjects\Index;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255|unique:collections,name|regex:/^[a-zA-Z_]+$/',
            'type' => ['required', new Enum(CollectionType::class)],
            'description' => 'nullable|string',
            'api_rules' => 'nullable|array',
            'api_rules.list' => 'nullable|string',
            'api_rules.view' => 'nullable|string',
            'api_rules.create' => 'nullable|string',
            'api_rules.update' => 'nullable|string',
            'api_rules.delete' => 'nullable|string',

            'fields' => 'required|array|min:1',
            'fields.*' => ['required', 'array'],
            'fields.*.name' => 'required|string|regex:/^[a-zA-Z_]+$/',
            'fields.*.type' => ['required', new Enum(CollectionFieldType::class)],
            'fields.*.nullable' => 'sometimes|boolean',
            'fields.*.unique' => 'sometimes|boolean',
            'fields.*.default' => 'sometimes',

            'indexes' => 'sometimes|array',
            'indexes.*' => ['required', 'array'],
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
        return collect($this->validated()['fields'])
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
            $type = CollectionType::tryFrom((string) $this->input('type'));
            $isAuthCollection = $type === CollectionType::Auth;
            $reservedNames = SchemaChangePlan::getAllReservedFields($isAuthCollection);
            $reservedDefinitions = SchemaChangePlan::getReservedFieldDefinitions($isAuthCollection);

            $seenNames = [];
            $fieldTypesByName = [];

            foreach ($reservedDefinitions as $reservedName => $definition) {
                $fieldTypesByName[$reservedName] = CollectionFieldType::from($definition['type']);
            }

            foreach ($this->input('fields', []) as $index => $field) {
                $fieldName = $field['name'] ?? null;

                if (is_string($fieldName)) {
                    if (in_array($fieldName, $reservedNames, true)) {
                        $validator->errors()->add("fields.{$index}.name", 'Reserved field names cannot be defined manually.');
                    }

                    if (isset($seenNames[$fieldName])) {
                        $validator->errors()->add("fields.{$index}.name", "Duplicate field name '{$fieldName}'.");
                    }

                    $seenNames[$fieldName] = true;
                }

                $fieldType = CollectionFieldType::tryFrom($field['type'] ?? '');
                if ($fieldType === null || ! is_array($field)) {
                    continue;
                }

                if (is_string($fieldName)) {
                    $fieldTypesByName[$fieldName] = $fieldType;
                }

                $unknownProperties = array_diff(array_keys($field), $fieldType->allowedProperties());

                if ($unknownProperties !== []) {
                    $validator->errors()->add(
                        "fields.{$index}",
                        'Unknown properties for field type '.$fieldType->value.': '.implode(', ', $unknownProperties)
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

    public function getIndexes(): array
    {
        $indexes = $this->validated()['indexes'] ?? [];

        return collect($indexes)
            ->values()
            ->map(fn (array $index): array => Index::fromArray($index)->toArray())
            ->all();
    }
}
