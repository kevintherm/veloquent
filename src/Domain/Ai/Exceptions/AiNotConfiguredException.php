<?php

namespace Veloquent\Core\Domain\Ai\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Veloquent\Core\Support\Traits\ApiResponse;

class AiNotConfiguredException extends Exception
{
    use ApiResponse;

    public function __construct()
    {
        parent::__construct('AI integration is not configured for this tenant.');
    }

    public function render($request): JsonResponse
    {
        return $this->errorResponse('AI prompt failed: ' . $this->getMessage(), 400);
    }
}
