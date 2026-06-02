<?php

namespace Veloquent\Core\Domain\Ai\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Veloquent\Core\Support\Traits\ApiResponse;

class MalformedResponseException extends Exception
{
    use ApiResponse;

    public function render($request): JsonResponse
    {
        return $this->errorResponse('AI prompt failed: Malformed response.', 500);
    }
}
