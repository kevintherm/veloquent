<?php

namespace Veloquent\Core\Domain\Records\Services;

use Illuminate\Http\Request;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Contracts\RuleContextBuilderInterface;

class UpdateRuleContextBuilder implements RuleContextBuilderInterface
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
        $record = $payload['record'];
        $data = $payload['data'];

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
