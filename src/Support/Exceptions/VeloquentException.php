<?php

namespace Veloquent\Core\Support\Exceptions;

use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
