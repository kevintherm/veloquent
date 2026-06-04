<?php

namespace Veloquent\Core\Domain\Ai\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Veloquent\Core\Support\Traits\ApiResponse;

class BlockedPromptException extends Exception
{
    use ApiResponse;

    public function __construct(
        string $message = "I'm sorry, I cannot fulfill that request.",
        private readonly int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY
    ) {
        parent::__construct($message);
    }


    public function render($request): JsonResponse
    {
        return $this->errorResponse($this->getMessage(), $this->statusCode);
    }
}
