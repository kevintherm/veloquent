<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;

class CreateCollectionAction
{
    public function execute(array $data): Collection
    {
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

        return Collection::create([
            ...$data,
            'fields' => $fields,
        ]);
    }
}
