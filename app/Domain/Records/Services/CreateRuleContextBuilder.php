<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Models\Collection;
use Illuminate\Http\Request;

class CreateRuleContextBuilder
{
    public function __construct(
        private readonly RuleContextBuilder $baseBuilder,
        private readonly ResolvesRuleContextRelations $relationResolver,
    ) {}

    public function build(Collection $collection, array $data, mixed $authenticatedUser, Request $request, ?string $rule = null): array
    {
        $context = $this->baseBuilder->build($request, $authenticatedUser);

        foreach ($collection->fields ?? [] as $field) {
            $fieldName = $field['name'];
            $context[$fieldName] = $data[$fieldName] ?? null;
        }

        if ($rule) {
            $this->relationResolver->resolve($collection, $context, $rule);
        }

        return $context;
    }
}
