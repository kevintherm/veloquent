<?php

namespace Veloquent\Core\Domain\Ai\Hooks;

use Closure;
use Veloquent\Core\Domain\RuleEngine\RuleEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Veloquent\Core\Domain\Hooks\Contracts\HookPipe;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\Ai\Services\ChatRuleContextBuilder;

class EvaluateChatApiRule implements HookPipe
{
    public function __construct(
        private readonly ChatRuleContextBuilder $contextBuilder
    ) {}

    /**
     * Handle the hook payload.
     *
     * @param HookPayload $payload
     * @param Closure $next
     * @return mixed
     * @throws AuthorizationException
     */
    public function handle(HookPayload $payload, Closure $next): mixed
    {
        $agent = $payload->record;
        $collection = $payload->collection;
        $user = $payload->actor;

        if ($user?->isSuperuser()) {
            return $next($payload);
        }

        $rule = $collection->api_rules['chat'] ?? null;
        if ($rule === null) {
            throw new AuthorizationException('Chat endpoint is restricted.');
        }

        $rule = trim($rule);
        if ($rule === '') {
            return $next($payload);
        }

        $context = $this->contextBuilder->build(
            $collection,
            ['agent' => $agent, 'data' => $payload->data],
            $user,
            $payload->request,
            $rule
        );

        $allowed = RuleEngine::make(array_keys($context))->evaluate($rule, $context);

        if (! $allowed) {
            throw new AuthorizationException('You are not authorized to chat with this agent.');
        }

        return $next($payload);
    }
}
