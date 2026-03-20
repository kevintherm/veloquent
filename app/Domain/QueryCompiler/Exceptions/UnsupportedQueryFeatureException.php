<?php

namespace App\Domain\QueryCompiler\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class UnsupportedQueryFeatureException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(501, $message);
    }
}
