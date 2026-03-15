<?php

namespace App\Domain\Collections\Requests;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
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

            foreach ($this->input('fields', []) as $index => $field) {
                $fieldName = $field['name'] ?? null;
                if (is_string($fieldName) && in_array($fieldName, $reservedNames, true)) {
                    $validator->errors()->add("fields.{$index}.name", 'Reserved field names cannot be defined manually.');
                }

                $fieldType = CollectionFieldType::tryFrom($field['type'] ?? '');
                if ($fieldType === null || ! is_array($field)) {
                    continue;
                }

                $unknownProperties = array_diff(array_keys($field), $fieldType->allowedProperties());

                if ($unknownProperties !== []) {
                    $validator->errors()->add(
                        "fields.{$index}",
                        'Unknown properties for field type '.$fieldType->value.': '.implode(', ', $unknownProperties)
                    );
                }
            }
        });
    }
}
