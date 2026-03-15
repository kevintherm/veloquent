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

        $fields = collect($data['fields'])
            ->map(fn ($field) => [
                'name' => $field['name'],
                'type' => $field['type'],
                'nullable' => $field['nullable'] ?? false,
                'unique' => $field['unique'] ?? false,
                'default' => $field['default'] ?? null,
                'length' => $field['length'] ?? null,
            ])
            ->values()
            ->all();

        $cleanedFields = SchemaChangePlan::cleanFields($fields, $isAuthCollection);

        if (isset($data['api_rules'])) {
            $allFields = SchemaChangePlan::mergeWithSystemFields($cleanedFields);
            $this->validateApiRules($data['api_rules'], Arr::pluck($allFields, 'name'));
        }

        return Collection::create([
            ...$data,
            'fields' => $cleanedFields,
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
