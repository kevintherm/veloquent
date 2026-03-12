<?php

namespace App\Domain\RuleEngine\Services;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class RuleEngineService
{
    public function __construct(
        private ExpressionLanguage $expressionLanguage
    ) {}

    public function allows(string $expression, array $parameters = []): bool
    {
        return (bool) $this->expressionLanguage->evaluate($expression, $parameters);
    }
}
