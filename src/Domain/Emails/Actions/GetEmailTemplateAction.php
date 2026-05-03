<?php

namespace Veloquent\Core\Domain\Emails\Actions;

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Emails\Models\EmailTemplate;
use Veloquent\Core\Domain\Emails\Services\EmailService;
use Veloquent\Core\Domain\Otp\Enums\OtpAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class GetEmailTemplateAction
{
    public function __construct(
        private readonly EmailService $emailService
    ) {}

    public function execute(Collection $collection, string $action): array
    {
        Gate::authorize('manage-schema');

        if ($collection->type !== CollectionType::Auth) {
            throw new AuthorizationException('This collection does not support email templates.');
        }

        $otpAction = OtpAction::tryFrom($action);

        if (! $otpAction) {
            throw new AuthorizationException('Invalid template action.');
        }

        $template = EmailTemplate::firstOrCreate(
            ['collection_id' => $collection->id, 'action' => $otpAction->value],
            ['content' => $this->emailService->getDefaultTemplate($otpAction->value)],
        );

        return [
            'action' => $otpAction->value,
            'label' => $otpAction->label(),
            'content' => $template->content,
        ];
    }
}
