<?php

namespace Veloquent\Core\Domain\Ai\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Veloquent\Core\Support\Traits\ApiResponse;

class AgentNotFoundException extends Exception
{
    use ApiResponse;

    public function __construct(string $agentIdentifier)
    {
        parent::__construct("Chatbot agent '{$agentIdentifier}' not found.");
    }

    public function render($request): JsonResponse
    {
        return $this->errorResponse($this->getMessage(), 404);
    }
}
