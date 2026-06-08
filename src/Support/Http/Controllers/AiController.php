<?php

namespace Veloquent\Core\Support\Http\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;
use Veloquent\Core\Domain\Ai\Contracts\AiService;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Support\Http\Controllers\ApiController;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;

class AiController extends ApiController
{
    /**
     * Handle the chatbot chat or prompt execution.
     */
    public function chat(Request $request, Collection $collection, AiService $aiService)
    {
        if ($collection->type !== CollectionType::Agents) {
            return $this->errorResponse('Collection is not of type agents.', Response::HTTP_BAD_REQUEST);
        }

        $data = $request->validate([
            'agent' => 'required|string',
            'prompt' => 'present|nullable|string',
            'messages' => 'nullable|array',
            'messages.*.role' => 'required|string|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // 10MB
            'stream' => 'nullable|boolean',
        ]);
        
        $data['prompt'] = $data['prompt'] ?? '';

        if ($request->hasFile('attachments')) {
            $data['attachments'] = Arr::wrap($request->file('attachments'));
        }

        $response = $aiService->chat($collection, $data);

        if ($response instanceof Response) {
            return $response;
        }

        if ($response instanceof Responsable) {
            return $response->toResponse($request);
        }

        return $this->successResponse($response);
    }
}
