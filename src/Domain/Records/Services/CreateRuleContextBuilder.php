<?php

namespace Veloquent\Core\Domain\Records\Services;

use Illuminate\Http\Request;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Contracts\RuleContextBuilder as RuleContextBuilderContract;

class CreateRuleContextBuilder implements RuleContextBuilderContract
{
    public function __construct(
        private readonly RuleContextBuilder $baseBuilder,
        private readonly ResolvesRuleContextRelations $relationResolver,
    ) {}

    public function build(
        Collection $collection,
        array $payload,
        mixed $authenticatedUser,
        ?Request $request = null,
        ?string $rule = null
    ): array {
        $context = $this->baseBuilder->build($request, $authenticatedUser);

        foreach ($collection->fields ?? [] as $field) {
            $fieldName = $field['name'];
            $context[$fieldName] = $payload[$fieldName] ?? null;
        }

        if ($rule) {
            $this->relationResolver->resolve($collection, $context, $rule);
        }

        return $context;
    }
}
