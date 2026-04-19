<?php

namespace App\Domain\QueryCompiler\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a rule/filter expression is syntactically invalid.
 */
class InvalidRuleExpressionException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
