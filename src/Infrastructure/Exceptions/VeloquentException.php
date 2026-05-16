<?php

namespace Veloquent\Core\Infrastructure\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

abstract class VeloquentException extends RuntimeException
{
    abstract protected function httpStatus(): int;

    abstract protected function errorCode(): string;

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => $this->errorCode(),
            'message' => $this->getMessage(),
        ], $this->httpStatus());
    }
}
