<?php

namespace Veloquent\Core\Support\Exceptions;

class InvalidArgumentException extends VeloquentException
{
    protected function httpStatus(): int
    {
        return 400;
    }

    protected function errorCode(): string
    {
        return 'INVALID_ARGUMENT';
    }
}
