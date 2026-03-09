<?php

namespace App\Domain\Collections\Requests;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('collections', 'name')->ignore($this->route('collection')->id, 'id'),
                'regex:/^[a-zA-Z_]+$/',
            ],
            'type' => ['sometimes', new Enum(CollectionType::class)],
            'description' => 'nullable|string',

            'fields' => 'sometimes|array|min:1',
            'fields.*' => ['required', 'array'],
            'fields.*.name' => 'required|string|regex:/^[a-zA-Z_]+$/',
            'fields.*.type' => ['required', new Enum(CollectionFieldType::class)],
            'fields.*.nullable' => 'sometimes|boolean',
            'fields.*.unique' => 'sometimes|boolean',
            'fields.*.default' => 'sometimes',
            'fields.*.length' => 'sometimes|integer|min:1',
        ];
    }

    public function getFields(): array
    {
        $fields = $this->validated()['fields'] ?? [];

        return collect($fields)
            ->map(fn ($field) => [
                'name' => $field['name'],
                'type' => $field['type'],
                'nullable' => $field['nullable'] ?? false,
                'unique' => $field['unique'] ?? false,
                'default' => $field['default'] ?? null,
                'length' => $field['length'] ?? null,
            ])
            ->values()
            ->all();
    }
}
