<?php

namespace Veloquent\Core\Domain\Collections\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Foundation\Http\FormRequest;
use Veloquent\Core\Domain\Collections\Enums\IndexType;
use Veloquent\Core\Domain\Collections\ValueObjects\Index;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;

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
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            ],
            'type' => ['sometimes', new Enum(CollectionType::class)],
            'description' => 'nullable|string',
            'force' => 'sometimes|boolean',
            'api_rules' => 'nullable|array',
            'api_rules.list' => 'nullable|string',
            'api_rules.view' => 'nullable|string',
            'api_rules.create' => 'nullable|string',
            'api_rules.update' => 'nullable|string',
            'api_rules.delete' => 'nullable|string',
            'api_rules.manage' => 'nullable|string',
            'api_rules.chat' => 'nullable|string',

            'fields' => 'sometimes|array|min:1',
            'fields.*' => ['required', 'array'],
            'fields.*.id' => 'sometimes|string',
            'fields.*.name' => 'required|string|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            'fields.*.type' => ['required', new Enum(CollectionFieldType::class)],
            'fields.*.nullable' => 'sometimes|boolean',
            'fields.*.unique' => 'sometimes',
            'fields.*.default' => 'sometimes',

            'indexes' => 'sometimes|array',
            'indexes.*' => ['required', 'array'],
            'indexes.*.name' => ['prohibited'],
            'indexes.*.columns' => ['required', 'array', 'min:1'],
            'indexes.*.columns.*' => ['required', 'string', 'regex:/^[a-zA-Z_]+$/'],
            'indexes.*.type' => ['required', new Enum(IndexType::class)],

            'options' => 'nullable|array',
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
                ])->only($type->allowedProperties())
                    ->except(['unique'])
                    ->all();
            })
            ->values()
            ->all();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fields = $this->input('fields', []);

            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldType = CollectionFieldType::tryFrom($field['type'] ?? '');
                if ($fieldType !== null) {
                    if ($fieldType === CollectionFieldType::File) {
                        $this->validateFileFieldDefinition($validator, $index, $field);
                    }
                }
            }
        });
    }

    private function validateFileFieldDefinition(Validator $validator, int $index, array $field): void
    {
        $multiple = (bool) ($field['multiple'] ?? false);
        $min = array_key_exists('min', $field) && $field['min'] !== null ? (int) $field['min'] : null;
        $max = array_key_exists('max', $field) && $field['max'] !== null ? (int) $field['max'] : null;

        if ($min !== null && $max !== null && $min > $max) {
            $validator->errors()->add("fields.{$index}.min", 'The minimum file count cannot be greater than the maximum file count.');
        }

        if (! $multiple) {
            if ($min !== null && $min > 1) {
                $validator->errors()->add("fields.{$index}.min", 'Single file fields cannot have min greater than 1.');
            }

            if ($max !== null && $max > 1) {
                $validator->errors()->add("fields.{$index}.max", 'Single file fields cannot have max greater than 1.');
            }
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
