<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Support\Arr;

class CreateCollectionAction
{
    public function execute(array $data): Collection
    {
        $isAuthCollection = ($data['type'] ?? null) === CollectionType::Auth->value;

        $mergedFields = SchemaChangePlan::mergeWithSystemFields($data['fields'], $isAuthCollection);

        if (isset($data['api_rules'])) {
            $this->validateApiRules($data['api_rules'], Arr::pluck($mergedFields, 'name'));
        }

        return Collection::create([
            ...$data,
            'fields' => $mergedFields,
            'indexes' => $data['indexes'] ?? [],
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
            QueryFilter::for($query, $fields)->lint($apiRules[$rule]);
        }
    }
}
