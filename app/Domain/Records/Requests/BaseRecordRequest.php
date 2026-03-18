<?php

namespace App\Domain\Records\Requests;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Field;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function getDynamicValidationRules(?Collection $collection = null, ?callable $intervene = null): array
    {
        $collection ??= $this->route('collection');

        $rules = [];
        $fields = $collection->fields ?? [];

        $autoFillFields = ['id', 'token_key', 'token', 'created_at', 'updated_at'];

        foreach ($fields as $field) {
            $fieldName = $field['name'];
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
}
