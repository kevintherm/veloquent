<?php

namespace Veloquent\Core\Domain\Emails\Controllers;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Emails\Actions\GetEmailTemplateAction;
use Veloquent\Core\Domain\Emails\Actions\UpdateEmailTemplateAction;
use Veloquent\Core\Support\Http\Controllers\ApiController;
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
