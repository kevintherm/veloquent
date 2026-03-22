<?php

namespace App\Domain\Records\Requests;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Field;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $collection = $this->route('collection');
        if (! $collection instanceof Collection) {
            return;
        }

        $payload = $this->all();

        foreach ($this->getRelationFields($collection) as $fieldName => $field) {
            if (! array_key_exists($fieldName, $payload)) {
                continue;
            }

            $payload[$fieldName] = $this->normalizeRelationInputValue(
                $payload[$fieldName]
            );
        }

        $this->replace($payload);
    }

    protected function getDynamicValidationRules(?Collection $collection = null, ?callable $intervene = null): array
    {
        $collection ??= $this->route('collection');

        $rules = [];
        $fields = $collection->fields ?? [];

        $autoFillFields = ['id', 'token', 'created_at', 'updated_at'];

        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $fieldType = CollectionFieldType::tryFrom((string) $field['type']);
            $fieldRules = [];

            if (! in_array($fieldName, $autoFillFields) && (! $field['nullable'] ?? false)) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if ($field['unique'] ?? false) {
                $uniqueRule = 'unique:'.$collection->getPhysicalTableName().','.$fieldName;
                if (method_exists($this, 'getRecordId') && $this->getRecordId()) {
                    $uniqueRule .= ','.$this->getRecordId();
                }
                $fieldRules[] = $uniqueRule;
            }

            if (isset($field['min']) && $field['min']) {
                $fieldRules[] = 'min:'.$field['min'];
            }

            if (isset($field['max']) && $field['max']) {
                $fieldRules[] = 'max:'.$field['max'];
            }

            if ($fieldType === CollectionFieldType::Relation) {
                $fieldRules[] = 'string';

                $fieldRules[] = function (string $attribute, mixed $value, callable $fail) use ($field): void {
                    if ($value === null || ! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $targetCollectionId = $field['target_collection_id'] ?? null;
                    if (! is_string($targetCollectionId) || $targetCollectionId === '') {
                        $fail('Relation field configuration is invalid.');

                        return;
                    }

                    $targetCollection = Collection::query()->find($targetCollectionId);
                    if ($targetCollection === null) {
                        $fail('Relation target collection does not exist.');

                        return;
                    }

                    $exists = Record::of($targetCollection)
                        ->newQuery()
                        ->where('id', trim($value))
                        ->exists();

                    if (! $exists) {
                        $fail('The selected related record does not exist.');
                    }
                };

                $rules[$fieldName] = $fieldRules;

                continue;
            }

            $fieldRules[] = $this->getFieldTypeRule($field['type']);
            $fieldRules = [...$fieldRules, ...$this->getSpecialFieldsRules($collection, $field)];

            if ($intervene) {
                $intervene($fieldName, $fieldRules);
            }

            $rules[$fieldName] = $fieldRules;
        }

        return $rules;
    }

    protected function getFieldTypeRule(string $fieldType): string
    {
        return CollectionFieldType::tryFrom($fieldType)?->recordValidationRule() ?? 'string';
    }

    protected function getSpecialFieldsRules(Collection $collection, Field $field): array
    {
        $isAuthCollection = $collection->type === CollectionType::Auth;
        if ($isAuthCollection && $field->name === 'email') {
            return ['email:rfc,dns,spoof'];
        }

        if ($isAuthCollection && $field->name === 'password') {
            return ['string', 'min:8'];
        }

        return [];
    }

    /**
     * Filter out auto-fill fields that have null values to prevent them from being set to null
     * in the database. This allows Laravel's auto-fill behavior to work properly.
     */
    protected function filterAutoFillFields(array $data, Collection $collection): array
    {
        $autoFillFields = ['id', 'token', 'created_at', 'updated_at'];

        return array_filter($data, function ($value, $key) use ($autoFillFields) {
            // Keep the field if it's not an auto-fill field
            if (! in_array($key, $autoFillFields)) {
                return true;
            }

            // Keep the field if it has a non-null value
            return $value !== null;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Filter out password field when it's null for auth collections
     */
    protected function filterPasswordField(array $data, Collection $collection): array
    {
        if ($collection->type === CollectionType::Auth && array_key_exists('password', $data) && $data['password'] === null) {
            unset($data['password']);
        }

        return $data;
    }

    protected function normalizeRelationFieldsForWrite(array $data, Collection $collection): array
    {
        foreach ($this->getRelationFields($collection) as $fieldName => $field) {
            if (! array_key_exists($fieldName, $data)) {
                continue;
            }

            $value = $data[$fieldName];

            if ($value === null) {
                continue;
            }

            $normalizedValue = $this->normalizeRelationInputValue($value);

            if (is_string($normalizedValue) && trim($normalizedValue) !== '') {
                $data[$fieldName] = trim($normalizedValue);
            } elseif (is_array($normalizedValue) && count($normalizedValue) > 0) {
                $data[$fieldName] = (string) $normalizedValue[0];
            } else {
                $data[$fieldName] = null;
            }
        }

        return $data;
    }

    private function getRelationFields(Collection $collection): array
    {
        return collect($collection->fields ?? [])
            ->filter(fn (Field|array $field): bool => ($field['type'] ?? null) === CollectionFieldType::Relation->value)
            ->keyBy(fn (Field|array $field): string => (string) $field['name'])
            ->all();
    }

    private function normalizeRelationInputValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value[0] ?? null;
        }

        return $value;
    }
}
