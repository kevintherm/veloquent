<?php

namespace Veloquent\Core\Infrastructure\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DomainValidationException extends VeloquentException
{
    public function __construct(private readonly array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function errorCode(): string
    {
        return 'VALIDATION_FAILED';
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => $this->errorCode(),
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], $this->httpStatus());
    }
}
