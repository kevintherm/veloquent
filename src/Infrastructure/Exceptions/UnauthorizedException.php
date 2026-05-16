<?php

namespace Veloquent\Core\Infrastructure\Exceptions;

class UnauthorizedException extends VeloquentException
{
    protected function httpStatus(): int
    {
        return 403;
    }

    protected function errorCode(): string
    {
        return 'UNAUTHORIZED';
    }
}
