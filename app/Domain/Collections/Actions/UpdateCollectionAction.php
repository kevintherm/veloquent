<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Validators\CollectionFieldValidator;
use App\Domain\QueryCompiler\Services\AllowedFieldsResolver;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateCollectionAction
{
    public function __construct(
        private readonly CollectionFieldValidator $collectionFieldValidator,
        private readonly AllowedFieldsResolver $allowedFieldsResolver,
    ) {}

    public function execute(Collection $collection, array $data): Collection
    {
        $defaultUsersCollectionName = config('velo.default_auth_collection');
        if (isset($data['name']) && $collection->name === $defaultUsersCollectionName && $data['name'] !== $defaultUsersCollectionName) {
            throw ValidationException::withMessages(['name' => 'Cannot rename default users collection']);
        }

        $existingFields = collect($collection->fields ?? [])
            ->map(fn (mixed $field): array => is_array($field) ? $field : (array) $field)
            ->values()
            ->all();

        $fieldsForRules = $data['fields'] ?? $existingFields;

        if (isset($data['fields']) && is_array($data['fields'])) {
            $existingFieldsWithIds = $this->ensureCollectionFieldsHaveIds($collection);
            $data['fields'] = $this->assignIdsForIncomingFields($data['fields'], $existingFieldsWithIds);
            $fieldsForRules = $data['fields'];
            $existingFields = $existingFieldsWithIds;
        }

        $indexesForValidation = $data['indexes'] ?? $collection->indexes ?? [];

        $this->collectionFieldValidator->validateForUpdate(
            $fieldsForRules,
            $existingFields,
            $indexesForValidation,
            $collection->type === CollectionType::Auth,
        );

        if (isset($data['fields']) && is_array($data['fields'])) {
            $data['fields'] = array_values(array_map(function (array $field): array {
                $fieldId = $field['id'] ?? null;

                if (! is_string($fieldId) || $fieldId === '') {
                    $field['id'] = SchemaChangePlan::generateFieldId();
                }

                return $field;
            }, $data['fields']));

            $fieldsForRules = $data['fields'];
        }

        if (isset($data['api_rules'])) {
            $this->validateApiRules($data['api_rules'], $fieldsForRules, $collection->type === CollectionType::Auth);
        }

        if (isset($data['options']) || $collection->type === CollectionType::Auth) {
            $data['options'] = $this->validateAuthOptions($data['options'] ?? $collection->options ?? [], Arr::pluck($fieldsForRules, 'name'), $collection->type === CollectionType::Auth);
        }

        $collection->update($data);
        $collection->refresh();

        return $collection;
    }

    private function ensureCollectionFieldsHaveIds(Collection $collection): array
    {
        $existingFields = collect($collection->fields ?? [])
            ->map(fn (mixed $field): array => is_array($field) ? $field : (array) $field)
            ->values();

        $hadMissingIds = false;

        $normalizedFields = $existingFields
            ->map(function (array $field) use (&$hadMissingIds): array {
                $fieldId = $field['id'] ?? null;

                if (! is_string($fieldId) || $fieldId === '') {
                    $field['id'] = SchemaChangePlan::generateFieldId();
                    $hadMissingIds = true;
                }

                return $field;
            })
            ->all();

        if ($hadMissingIds) {
            $collection->forceFill(['fields' => $normalizedFields])->saveQuietly();
            $collection->refresh();
        }

        return collect($collection->fields ?? [])
            ->map(fn (mixed $field): array => is_array($field) ? $field : (array) $field)
            ->values()
            ->all();
    }

    private function assignIdsForIncomingFields(array $incomingFields, array $existingFields): array
    {
        $existingById = collect($existingFields)
            ->filter(fn (array $field): bool => isset($field['id']) && is_string($field['id']) && $field['id'] !== '')
            ->keyBy('id');

        $existingByName = collect($existingFields)
            ->filter(fn (array $field): bool => isset($field['name']) && is_string($field['name']))
            ->keyBy('name');

        return collect($incomingFields)
            ->map(function (array $field) use ($existingById, $existingByName): array {
                $fieldId = $field['id'] ?? null;

                if (is_string($fieldId) && $fieldId !== '') {
                    if ($existingById->has($fieldId)) {
                        return $field;
                    }

                    $field['id'] = SchemaChangePlan::generateFieldId();

                    return $field;
                }

                $fieldName = $field['name'] ?? null;
                $fieldType = $field['type'] ?? null;

                if (is_string($fieldName) && $existingByName->has($fieldName)) {
                    $existingField = $existingByName->get($fieldName);
                    $existingFieldId = $existingField['id'] ?? null;
                    $existingFieldType = $existingField['type'] ?? null;

                    if (
                        is_string($existingFieldId)
                        && $existingFieldId !== ''
                        && is_string($fieldType)
                        && is_string($existingFieldType)
                        && $fieldType === $existingFieldType
                    ) {
                        $field['id'] = $existingFieldId;

                        return $field;
                    }
                }

                $field['id'] = SchemaChangePlan::generateFieldId();

                return $field;
            })
            ->values()
            ->all();
    }

    public function validateAuthOptions(array $options, array $fields, bool $isAuthCollection): array
    {
        if (! $isAuthCollection) {
            return $options;
        }

        // Default structure for auth collections
        $options['auth_methods'] ??= [];
        $options['auth_methods']['standard'] ??= [];
        $options['auth_methods']['standard']['enabled'] ??= true;
        $options['auth_methods']['standard']['identity_fields'] ??= ['email'];

        $options['auth_methods']['oauth'] ??= [];
        $options['auth_methods']['oauth']['enabled'] ??= false;

        $validator = Validator::make($options, [
            'auth_methods' => 'required|array',

            'auth_methods.standard' => 'required|array',
            'auth_methods.standard.enabled' => 'required|boolean',
            'auth_methods.standard.identity_fields' => 'required|array|min:1',
            'auth_methods.standard.identity_fields.*' => ['string', Rule::in($fields)],

            'auth_methods.oauth' => 'required|array',
            'auth_methods.oauth.enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function validateApiRules(array $apiRules, array $fields, bool $isAuthCollection): void
    {
        $expectedKeys = ['list', 'create', 'view', 'update', 'delete'];
        if ($isAuthCollection) {
            $expectedKeys[] = 'manage';
        }

        $missingKeys = array_diff($expectedKeys, array_keys($apiRules));

        if (! empty($missingKeys)) {
            throw ValidationException::withMessages(['api_rules' => 'Missing API rules for: '.implode(', ', $missingKeys)]);
        }

        $allowedFields = $this->allowedFieldsResolver->resolveFromFieldDefinitions($fields);

        foreach ($expectedKeys as $rule) {
            $query = app(Collection::class)->newQuery();
            $inMemory = in_array($rule, ['create', 'update', 'manage'], true);
            QueryFilter::for($query, $allowedFields)->lint($apiRules[$rule], $inMemory);
        }
    }
}
