<?php

namespace App\Domain\Collections\Controllers;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CollectionController extends ApiController
{
    public function index(): JsonResponse
    {
        $collections = Collection::get();

        return $this->successResponse($collections);
    }

    public function show(Collection $collection): JsonResponse
    {
        return $this->successResponse($collection);
    }

    public function store(Request $request): JsonResponse
    {
        $valid = $request->validate([
            'name' => 'required|string|max:255|unique:collections,name|regex:/^[a-zA-Z_]+$/',
            'type' => ['required', new Enum(CollectionType::class)],
            'description' => 'nullable|string',

            'fields' => 'required|array|min:1',
            'fields.*' => ['required', 'array'],
            'fields.*.name' => 'required|string|regex:/^[a-zA-Z_]+$/',
            'fields.*.type' => ['required', new Enum(CollectionFieldType::class)],
            'fields.*.nullable' => 'sometimes|boolean',
            'fields.*.unique' => 'sometimes|boolean',
            'fields.*.default' => 'sometimes',
            'fields.*.length' => 'sometimes|integer|min:1',
        ]);

        $fields = collect($valid['fields'])
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

        $collection = Collection::create([
            ...$valid,
            'fields' => $fields,
        ]);

        return $this->successResponse($collection);
    }

    public function update(Request $request, Collection $collection): JsonResponse
    {
        $valid = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('collections', 'name')->ignore($collection->id, 'id'),
                'regex:/^[a-zA-Z_]+$/',
            ],
            'type' => ['required', new Enum(CollectionType::class)],
            'description' => 'nullable|string',

            'fields' => 'required|array',
            'fields.*' => ['required', 'array'],
            'fields.*.name' => 'required|string|regex:/^[a-zA-Z_]+$/',
            'fields.*.type' => ['required', new Enum(CollectionFieldType::class)],
            'fields.*.nullable' => 'sometimes|boolean',
            'fields.*.unique' => 'sometimes|boolean',
            'fields.*.default' => 'sometimes',
            'fields.*.length' => 'sometimes|integer|min:1',
        ]);

        $fields = collect($valid['fields'])
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

        $collection->update([
            ...$valid,
            'fields' => $fields,
        ]);

        $collection->refresh();

        return $this->successResponse($collection);
    }

    public function destroy(Collection $collection): JsonResponse
    {
        $collection->delete();

        return $this->successResponse([]);
    }
}
