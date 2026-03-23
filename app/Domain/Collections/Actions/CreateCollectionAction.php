<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Validators\CollectionFieldValidator;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Support\Arr;

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

        $options = $data['options'] ?? [];
        if ($isAuthCollection) {
            $options = array_merge([
                'auth_methods' => ['email_password'],
                'require_email_verification' => false,
            ], $options);
        }

        return Collection::create([
            ...$data,
            'table_name' => SchemaChangePlan::generateTableName($data['name'], $data['is_system'] ?? false),
            'fields' => $mergedFields,
            'indexes' => $indexes,
            'options' => $options ?: null,
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
}
