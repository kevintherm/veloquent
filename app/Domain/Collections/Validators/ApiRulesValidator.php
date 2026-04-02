<?php

namespace App\Domain\Collections\Validators;

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\AllowedFieldsResolver;
use App\Domain\QueryCompiler\Services\QueryFilter;
use Illuminate\Validation\ValidationException;

class ApiRulesValidator
{
    public function __construct(
        private readonly AllowedFieldsResolver $allowedFieldsResolver,
    ) {}

    public function validate(array $apiRules, array $fields, bool $isAuthCollection): array
    {
        $requiredApiKeys = ['list', 'create', 'view', 'update', 'delete'];
        $inMemoryActions = ['create', 'update', 'manage'];

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
            $inMemory = in_array($rule, $inMemoryActions, true);
            QueryFilter::for(Collection::query(), $allowedFields)->lint($apiRules[$rule], $inMemory);
        }

        $validated = array_intersect_key($apiRules, array_flip($requiredApiKeys));

        return $validated;
    }
}
