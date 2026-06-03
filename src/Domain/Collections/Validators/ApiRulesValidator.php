<?php

namespace Veloquent\Core\Domain\Collections\Validators;

use Illuminate\Validation\ValidationException;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\QueryCompiler\Services\QueryFilter;
use Veloquent\Core\Domain\QueryCompiler\Services\AllowedFieldsResolver;

class ApiRulesValidator
{
    public function __construct(
        private readonly AllowedFieldsResolver $allowedFieldsResolver,
    ) {}

    public function validate(array $apiRules, array $fields, CollectionType|string $collectionType): array
    {
        $requiredApiKeys = ['list', 'create', 'view', 'update', 'delete'];

        $typeEnum = $collectionType instanceof CollectionType
            ? $collectionType
            : CollectionType::from((string) $collectionType);

        $requiredApiKeys = array_merge($requiredApiKeys, $typeEnum->additionalRules());
        $allExpectedKeys = $requiredApiKeys;

        $missingKeys = array_diff($requiredApiKeys, array_keys($apiRules));

        if (! empty($missingKeys)) {
            throw ValidationException::withMessages([
                'api_rules' => ['Missing API rules for: '.implode(', ', $missingKeys)],
            ]);
        }

        $allowedFields = $this->allowedFieldsResolver->resolveFromFieldDefinitions($fields);

        foreach ($apiRules as $key => $ruleValue) {
            if (in_array($key, $allExpectedKeys) && $ruleValue !== null) {
                QueryFilter::for(Collection::query(), $allowedFields)->lint($ruleValue);
            }
        }

        $validated = array_intersect_key($apiRules, array_flip($allExpectedKeys));

        return $validated;
    }
}
