<?php

namespace App\Domain\Records\Requests;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRecordRequest extends FormRequest
{
    protected Collection $collection;

    public function authorize(): bool
    {
        $this->collection = $this->route('collection');

        // Allow superusers to access any collection
        $jwtService = app(\App\Domain\Auth\Services\JwtAuthService::class);
        $token = $jwtService->extractTokenFromRequest($this);

        $user = null;
        if ($token) {
            try {
                $user = $jwtService->authenticate($token);
            } catch (\Exception $e) {
            }
        }

        $isSuperuser = $user && $user->collection?->name === 'superusers';
        if ($isSuperuser) {
            return true;
        }

        // Protect system collections
        if ($this->collection->is_system) {
            return false;
        }

        // Protect auth collections from direct record manipulation
        if ($this->collection->type === CollectionType::Auth) {
            return false;
        }

        return true;
    }

    protected function getDynamicValidationRules(): array
    {
        $rules = [];
        $fields = $this->collection->fields ?? [];

        foreach ($fields as $field) {
            $fieldRules = [];

            // Basic type validation
            $fieldRules[] = $this->getFieldTypeRule($field['type']);

            // Nullable validation
            if (! $field['nullable']) {
                $fieldRules[] = 'required';
            }

            // Unique validation
            if ($field['unique']) {
                $fieldRules[] = 'unique:'.$this->collection->getPhysicalTableName().','.$field['name'];
            }

            // Length validation
            if (isset($field['length']) && $field['length']) {
                $fieldRules[] = 'max:'.$field['length'];
            }

            $rules[$field['name']] = $fieldRules;
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
