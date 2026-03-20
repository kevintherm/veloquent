<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class UpdateCollectionAction
{
    public function execute(Collection $collection, array $data): Collection
    {
        $defaultUsersCollectionName = config('velo.default_auth_collection');
        if (isset($data['name']) && $collection->name === $defaultUsersCollectionName && $data['name'] !== $defaultUsersCollectionName) {
            throw ValidationException::withMessages(['name' => 'Cannot rename default users collection']);
        }

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
            throw ValidationException::withMessages(['api_rules' => 'Missing API rules for: '.implode(', ', $missingKeys)]);
        }

        foreach ($expectedKeys as $rule) {
            $query = app(Collection::class)->newQuery();
            QueryFilter::for($query, $fields)->lint($apiRules[$rule]);
        }
    }
}
