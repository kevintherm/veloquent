<?php

namespace Veloquent\Core\Domain\Collections\Validators;

use Illuminate\Validation\ValidationException;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\QueryCompiler\Services\QueryFilter;
use Veloquent\Core\Domain\QueryCompiler\Services\AllowedFieldsResolver;

class ApiRulesValidator
{
    public function __construct(
        private readonly AllowedFieldsResolver $allowedFieldsResolver,
    ) {}

    public function validate(array $apiRules, array $fields, bool $isAuthCollection): array
    {
        $requiredApiKeys = ['list', 'create', 'view', 'update', 'delete'];

        if ($isAuthCollection) {
            $requiredApiKeys[] = 'manage';
        }

        $missingKeys = array_diff($requiredApiKeys, array_keys($apiRules));

        if (! empty($missingKeys)) {
            throw ValidationException::withMessages([
                'api_rules' => ['Missing API rules for: '.implode(', ', $missingKeys)],
            ]);
        }

        $allowedFields = $this->allowedFieldsResolver->resolveFromFieldDefinitions($fields);

        foreach ($requiredApiKeys as $rule) {
            QueryFilter::for(Collection::query(), $allowedFields)->lint($apiRules[$rule]);
        }

        $validated = array_intersect_key($apiRules, array_flip($requiredApiKeys));

        return $validated;
    }
}
