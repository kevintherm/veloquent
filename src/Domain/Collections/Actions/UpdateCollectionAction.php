<?php

namespace Veloquent\Core\Domain\Collections\Actions;

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Validators\ApiRulesValidator;
use Veloquent\Core\Domain\Collections\Validators\AuthOptionsValidator;
use Veloquent\Core\Domain\Collections\Validators\CollectionFieldValidator;
use Veloquent\Core\Domain\Collections\Validators\CollectionValidator;
use Veloquent\Core\Domain\SchemaManagement\Contracts\CollectionSyncService;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class UpdateCollectionAction
{
    public function __construct(
        private readonly CollectionFieldValidator $collectionFieldValidator,
        private readonly CollectionValidator $collectionValidator,
        private readonly ApiRulesValidator $apiRulesValidator,
        private readonly AuthOptionsValidator $authOptionsValidator,
        private readonly CollectionSyncService $syncService
    ) {}

    public function execute(Collection $collection, array $data, bool $force = false, bool $skipValidation = false): Collection
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

        if (!$skipValidation) {
            $this->collectionFieldValidator->validateForUpdate(
                $fieldsForRules,
                $existingFields,
                $indexesForValidation,
                $collection->type->value,
            );

            $this->collectionValidator->validateUpdate(
                $collection,
                $fieldsForRules,
                $force
            )->throwIfFailed();
        }

        if (isset($data['fields']) && is_array($data['fields'])) {
            $data['fields'] = array_values(array_map(function (array $field): array {
                $fieldId = $field['id'] ?? null;

                if (! is_string($fieldId) || $fieldId === '') {
                    $field['id'] = SchemaChange::generateFieldId();
                }

                return $field;
            }, $data['fields']));

            $fieldsForRules = $data['fields'];
        }

        if (isset($data['api_rules'])) {
            $data['api_rules'] = $this->apiRulesValidator->validate(
                $data['api_rules'],
                $fieldsForRules,
                $collection->type
            );
        }

        if (isset($data['options']) || $collection->type === CollectionType::Auth) {
            $data['options'] = $this->authOptionsValidator->validate(
                $data['options'] ?? $collection->options ?? [],
                Arr::pluck($fieldsForRules, 'name'),
                $collection->type === CollectionType::Auth
            );
        }

        return $this->syncService->update($collection, [
            ...$data,
            'fields' => $fieldsForRules,
            'indexes' => $indexesForValidation,
        ]);
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
                    $field['id'] = SchemaChange::generateFieldId();
                    $hadMissingIds = true;
                }

                if (($field['type'] ?? '') === \Veloquent\Core\Domain\Collections\Enums\CollectionFieldType::RelationMany->value && isset($field['pivot_fields'])) {
                    $field['pivot_fields'] = collect($field['pivot_fields'])->map(function ($pf) use (&$hadMissingIds) {
                        if (!isset($pf['id']) || $pf['id'] === '') {
                            $pf['id'] = SchemaChange::generateFieldId();
                            $hadMissingIds = true;
                        }
                        return $pf;
                    })->all();
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
                        $matchedField = $existingById->get($fieldId);
                    } else {
                        $field['id'] = SchemaChange::generateFieldId();
                        $matchedField = null;
                    }
                } else {
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
                            $matchedField = $existingField;
                        } else {
                            $field['id'] = SchemaChange::generateFieldId();
                            $matchedField = null;
                        }
                    } else {
                        $field['id'] = SchemaChange::generateFieldId();
                        $matchedField = null;
                    }
                }

                // Handle pivot fields
                if (($field['type'] ?? '') === \Veloquent\Core\Domain\Collections\Enums\CollectionFieldType::RelationMany->value && isset($field['pivot_fields'])) {
                    $existingPivotFields = $matchedField['pivot_fields'] ?? [];
                    $field['pivot_fields'] = $this->assignPivotIds($field['pivot_fields'], $existingPivotFields);
                }

                return $field;
            })
            ->values()
            ->all();
    }

    private function assignPivotIds(array $incoming, array $existing): array
    {
        $existingById = collect($existing)->filter(fn($f) => isset($f['id']))->keyBy('id');
        $existingByName = collect($existing)->filter(fn($f) => isset($f['name']))->keyBy('name');

        return collect($incoming)->map(function ($pf) use ($existingById, $existingByName) {
            if (is_string($pf)) {
                $pf = ['name' => $pf, 'type' => 'text'];
            }

            $id = $pf['id'] ?? null;
            if ($id && $existingById->has($id)) {
                return $pf;
            }

            $name = $pf['name'] ?? null;
            if ($name && $existingByName->has($name)) {
                $pf['id'] = $existingByName->get($name)['id'];
                return $pf;
            }

            $pf['id'] = SchemaChange::generateFieldId();
            return $pf;
        })->all();
    }
}
