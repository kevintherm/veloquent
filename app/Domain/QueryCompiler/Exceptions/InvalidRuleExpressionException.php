<?php

namespace App\Domain\QueryCompiler\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class InvalidRuleExpressionException extends Exception
{
    public function report(): void
    {
        Log::error('InvalidRuleExpressionException: '.$this->getMessage());
    }
}
