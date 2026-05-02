<?php

namespace App\Domain\Emails\Controllers;

use App\Domain\Collections\Models\Collection;
use App\Domain\Emails\Actions\GetEmailTemplateAction;
use App\Domain\Emails\Actions\UpdateEmailTemplateAction;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends ApiController
{
    public function __construct(
        private readonly GetEmailTemplateAction $getAction,
        private readonly UpdateEmailTemplateAction $updateAction,
    ) {}

    public function show(Collection $collection, string $action): JsonResponse
    {
        $data = $this->getAction->execute($collection, $action);

        return $this->successResponse($data);
    }

    public function update(Request $request, Collection $collection, string $action): JsonResponse
    {
        $request->validate([
            'content' => ['nullable', 'string'],
        ]);

        $this->updateAction->execute(
            $collection,
            $action,
            $request->input('content')
        );

        return $this->successResponse([], 'Template updated successfully.');
    }
}
