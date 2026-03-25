<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Validators\CollectionFieldValidator;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateCollectionAction
{
    public function __construct(
        private readonly CollectionFieldValidator $collectionFieldValidator,
    ) {}

    public function execute(array $data): Collection
    {
        $collectionType = $data['type'] ?? null;

        if ($collectionType instanceof CollectionType) {
            $collectionType = $collectionType->value;
        }

        $isAuthCollection = $collectionType === CollectionType::Auth->value;
        $mergedFields = SchemaChangePlan::mergeWithSystemFields($data['fields'], $isAuthCollection);
        $indexes = $data['indexes'] ?? [];

        $this->collectionFieldValidator->validateForCreate(
            $data['fields'] ?? [],
            $indexes,
            $isAuthCollection,
        );

        if (isset($data['api_rules'])) {
            $this->validateApiRules($data['api_rules'], Arr::pluck($mergedFields, 'name'));
        }

        if (isset($data['options']) || $isAuthCollection) {
            $data['options'] = $this->validateAuthOptions($data['options'] ?? [], Arr::pluck($mergedFields, 'name'), $isAuthCollection);
        }

        return Collection::create([
            ...$data,
            'table_name' => SchemaChangePlan::generateTableName($data['name'], $data['is_system'] ?? false),
            'fields' => $mergedFields,
            'indexes' => $indexes,
        ]);
    }

    private function validateApiRules(array $apiRules, array $fields): void
    {
        $expectedKeys = ['list', 'create', 'view', 'update', 'delete'];
        $missingKeys = array_diff($expectedKeys, array_keys($apiRules));

        if (! empty($missingKeys)) {
            throw new \InvalidArgumentException('Missing API rules for: '.implode(', ', $missingKeys));
        }

        foreach ($expectedKeys as $rule) {
            $query = app(Collection::class)->newQuery();
            $inMemory = in_array($rule, ['create', 'update'], true);
            QueryFilter::for($query, $fields)->lint($apiRules[$rule], $inMemory);
        }
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

            'require_email_verification' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
