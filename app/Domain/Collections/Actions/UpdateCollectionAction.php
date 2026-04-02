<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Validators\ApiRulesValidator;
use App\Domain\Collections\Validators\AuthOptionsValidator;
use App\Domain\Collections\Validators\CollectionFieldValidator;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class UpdateCollectionAction
{
    public function __construct(
        private readonly CollectionFieldValidator $collectionFieldValidator,
        private readonly ApiRulesValidator $apiRulesValidator,
        private readonly AuthOptionsValidator $authOptionsValidator
    ) {}

    public function execute(Collection $collection, array $data): Collection
    {
        $defaultUsersCollectionName = config('velo.default_auth_collection', 'users');
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
            $data['api_rules'] = $this->apiRulesValidator->validate(
                $data['api_rules'],
                $fieldsForRules,
                $collection->type === CollectionType::Auth
            );
        }

        if (isset($data['options']) || $collection->type === CollectionType::Auth) {
            $data['options'] = $this->authOptionsValidator->validate(
                $data['options'] ?? $collection->options ?? [],
                Arr::pluck($fieldsForRules, 'name'),
                $collection->type === CollectionType::Auth
            );
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
}
