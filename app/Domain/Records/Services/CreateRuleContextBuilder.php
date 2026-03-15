<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Http\Request;

class CreateRuleContextBuilder
{
    public function build(Collection $collection, array $data, mixed $authenticatedUser, Request $request): array
    {
        $authContext = null;

        if ($authenticatedUser instanceof Record) {
            $authContext = $authenticatedUser->getAttributes();
        }

        $context = [
            'request' => [
                'body' => $request->all(),
                'param' => $request->route()?->parameters() ?? [],
                'query' => $request->query(),
                'auth' => $authContext,
            ],
        ];

        foreach ($collection->fields ?? [] as $field) {
            $fieldName = $field['name'];
            $context[$fieldName] = $data[$fieldName] ?? null;
        }

        return $context;
    }
}
