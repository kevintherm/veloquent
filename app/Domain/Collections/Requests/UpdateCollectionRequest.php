<?php

namespace App\Domain\Collections\Requests;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
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
                'required',
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
            if (! $this->has('fields')) {
                return;
            }

            /** @var Collection $collection */
            $collection = $this->route('collection');
            $isAuthCollection = $collection->type === CollectionType::Auth;
            $reservedDefinitions = SchemaChangePlan::getReservedFieldDefinitions($isAuthCollection);

            $fields = $this->input('fields', []);
            $fieldsByName = collect($fields)
                ->filter(fn (mixed $field) => is_array($field) && isset($field['name']))
                ->keyBy('name')
                ->all();

            foreach (array_keys($reservedDefinitions) as $reservedName) {
                if (! array_key_exists($reservedName, $fieldsByName)) {
                    $validator->errors()->add('fields', "Reserved field '{$reservedName}' must be present.");
                }
            }

            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldType = CollectionFieldType::tryFrom($field['type'] ?? '');
                if ($fieldType !== null) {
                    $unknownProperties = array_diff(array_keys($field), $fieldType->allowedProperties());

                    if ($unknownProperties !== []) {
                        $validator->errors()->add(
                            "fields.{$index}",
                            'Unknown properties for field type '.$fieldType->value.': '.implode(', ', $unknownProperties)
                        );
                    }
                }

                $fieldName = $field['name'] ?? null;
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

                unset($normalizedIncoming['order'], $normalizedCanonical['order']);

                if ($normalizedIncoming !== $normalizedCanonical) {
                    $validator->errors()->add(
                        "fields.{$index}",
                        "Reserved field '{$fieldName}' cannot be modified except for order."
                    );
                }
            }
        });
    }
}
