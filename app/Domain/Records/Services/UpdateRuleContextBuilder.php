<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Http\Request;

class UpdateRuleContextBuilder
{
    public function __construct(
        private readonly RuleContextBuilder $baseBuilder,
        private readonly ResolvesRuleContextRelations $relationResolver,
    ) {}

    public function build(Collection $collection, Record $record, array $data, mixed $authenticatedUser, Request $request, ?string $rule = null): array
    {
        $context = $this->baseBuilder->build($request, $authenticatedUser);

        $recordData = $record->toArray();

        foreach ($collection->fields ?? [] as $field) {
            $fieldName = $field['name'];
            $context[$fieldName] = array_key_exists($fieldName, $data)
                ? $data[$fieldName]
                : ($recordData[$fieldName] ?? null);
        }

        if ($rule) {
            $this->relationResolver->resolve($collection, $context, $rule);
        }

        return $context;
    }
}
