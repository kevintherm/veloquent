<?php

namespace App\Domain\QueryCompiler\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a rule/filter expression is syntactically invalid.
 */
class InvalidRuleExpressionException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(400, $message);
    }
}
