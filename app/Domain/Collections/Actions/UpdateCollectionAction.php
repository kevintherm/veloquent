<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use Illuminate\Support\Arr;

class UpdateCollectionAction
{
    public function execute(Collection $collection, array $data): Collection
    {
        $fieldsForRules = $data['fields'] ?? $collection->fields;

        if (isset($data['api_rules'])) {
            $this->validateApiRules($data['api_rules'], Arr::pluck($fieldsForRules, 'name'));
        }

        $collection->update($data);
        $collection->refresh();

        return $collection;
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
