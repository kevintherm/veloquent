<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;

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

        $collection->update($data);
        $collection->refresh();

        return $collection;
    }
}
