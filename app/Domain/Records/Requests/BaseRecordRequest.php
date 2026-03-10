<?php

namespace App\Domain\Records\Requests;

use App\Domain\Collections\Models\Collection;
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

        $autoFillFields = ['id', 'created_at', 'updated_at'];

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

            if (isset($field['length']) && $field['length']) {
                $fieldRules[] = 'max:'.$field['length'];
            }

            $fieldRules[] = $this->getFieldTypeRule($field['type']);

            if ($intervene) {
                $intervene($fieldName, $fieldRules);
            }

            $rules[$fieldName] = $fieldRules;
        }

        return $rules;
    }

    protected function getFieldTypeRule(string $fieldType): string
    {
        return match ($fieldType) {
            'string' => 'string',
            'text' => 'string',
            'integer' => 'integer',
            'bigint' => 'integer',
            'tinyint' => 'integer',
            'float' => 'numeric',
            'double' => 'numeric',
            'decimal' => 'numeric',
            'boolean' => 'boolean',
            'datetime' => 'date',
            'timestamp' => 'date',
            'json' => 'json',
            'longtext' => 'string',
            'char' => 'string',
            default => 'string',
        };
    }
}
