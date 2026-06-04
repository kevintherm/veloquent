<?php

namespace Veloquent\Core\Domain\Ai\Services;

use Illuminate\Http\Request;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Contracts\RuleContextBuilder as RuleContextBuilderContract;
use Veloquent\Core\Domain\Records\Services\ResolvesRuleContextRelations;
use Veloquent\Core\Domain\Records\Services\RuleContextBuilder;

class ChatRuleContextBuilder implements RuleContextBuilderContract
{
    public function __construct(
        private readonly RuleContextBuilder $baseBuilder,
        private readonly ResolvesRuleContextRelations $relationResolver,
    ) {}

    /**
     * Build the evaluation context for an AI chat rule.
     *
     * @param Collection $collection The agents collection
     * @param array $payload Must contain ['agent' => \App\Domain\Records\Models\Record::class, 'data' => array]
     * @param mixed $authenticatedUser
     * @param Request|null $request
     * @param string|null $rule
     * @return array
     */
    public function build(
        Collection $collection,
        array $payload,
        mixed $authenticatedUser,
        ?Request $request = null,
        ?string $rule = null
    ): array {
        $context = $this->baseBuilder->build($request, $authenticatedUser);

        $agent = $payload['agent'] ?? null;
        $agentData = $agent ? $agent->toArray() : [];

        foreach ($collection->fields ?? [] as $field) {
            $fieldName = $field['name'];
            $context[$fieldName] = $agentData[$fieldName] ?? null;
        }

        $context['request']['body']['prompt'] = $payload['data']['prompt'] ?? ($request ? $request->input('prompt') : null);
        $context['request']['body']['messages'] = $payload['data']['messages'] ?? ($request ? $request->input('messages', []) : []);

        if ($rule) {
            $this->relationResolver->resolve($collection, $context, $rule);
        }

        return $context;
    }
}
