<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Http\Request;

class UpdateRuleContextBuilder
{
    public function build(Collection $collection, Record $record, array $data, mixed $authenticatedUser, Request $request): array
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
            $context[$fieldName] = $record->getAttribute($fieldName);
        }

        return $context;
    }
}
