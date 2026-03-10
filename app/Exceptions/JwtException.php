<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class JwtException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        Log::error('JwtException: '.$this->getMessage());
    }
}
