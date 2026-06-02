<?php

namespace Veloquent\Core\Support\Http\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;
use Veloquent\Core\Domain\Ai\Services\AiService;
use Veloquent\Core\Support\Http\Controllers\ApiController;

class AiController extends ApiController
{
    /**
     * Handle the chatbot chat or prompt execution.
     */
    public function chat(Request $request, AiService $aiService)
    {
        $data = $request->validate([
            'agent' => 'required|string',
            'prompt' => 'required|string',
            'messages' => 'nullable|array',
            'messages.*.role' => 'required|string|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // Limit to 10MB per attachment
            'output_type' => 'nullable|string|in:text,json',
            'schema' => 'nullable|array',
            'stream' => 'nullable|boolean',
        ]);

        if ($request->hasFile('attachments')) {
            $data['attachments'] = Arr::wrap($request->file('attachments'));
        }

        $response = $aiService->chat($data);

        if ($response instanceof Response) {
            return $response;
        }

        if ($response instanceof Responsable) {
            return $response->toResponse($request);
        }

        return $this->successResponse($response);
    }
}
