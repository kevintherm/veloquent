<?php

namespace App\Http\Controllers;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Otp\Enums\OtpAction;
use App\EmailTemplate;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailTemplateController extends ApiController
{
    public function show(Collection $collection, string $action): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support email templates.', Response::HTTP_FORBIDDEN);
        }

        $otpAction = OtpAction::tryFrom($action);

        if (! $otpAction) {
            return $this->errorResponse('Invalid template action.', Response::HTTP_NOT_FOUND);
        }

        $template = EmailTemplate::firstOrCreate(
            ['collection_id' => $collection->id, 'action' => $otpAction->value],
            ['content' => $otpAction->defaultTemplate()],
        );

        return $this->successResponse([
            'action' => $otpAction->value,
            'label' => $otpAction->label(),
            'content' => $template->content,
        ]);
    }

    public function update(Request $request, Collection $collection, string $action): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support email templates.', Response::HTTP_FORBIDDEN);
        }

        $otpAction = OtpAction::tryFrom($action);

        if (! $otpAction) {
            return $this->errorResponse('Invalid template action.', Response::HTTP_NOT_FOUND);
        }

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        $content = trim(strip_tags($request->input('content'))) === ''
            ? $otpAction->defaultTemplate()
            : $request->input('content');

        EmailTemplate::updateOrCreate(
            ['collection_id' => $collection->id, 'action' => $otpAction->value],
            ['content' => $content],
        );

        return $this->successResponse([], 'Template updated successfully.');
    }
}
