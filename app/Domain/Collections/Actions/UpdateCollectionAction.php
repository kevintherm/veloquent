<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Support\Arr;

class UpdateCollectionAction
{
    public function execute(Collection $collection, array $data): Collection
    {
        if (isset($data['fields'])) {
            $data['fields'] = collect($data['fields'])
                ->map(fn ($field) => [
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'api_rules' => $field['api_rules'] ?? [],
                    'nullable' => $field['nullable'] ?? false,
                    'unique' => $field['unique'] ?? false,
                    'default' => $field['default'] ?? null,
                    'length' => $field['length'] ?? null,
                ])
                ->values()
                ->all();
        }

        $allFields = SchemaChangePlan::mergeWithSystemFields(array_merge($collection->fields, $data['fields']));
        $this->validateApiRules($data['api_rules'], Arr::pluck($allFields, 'name'));
        
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
