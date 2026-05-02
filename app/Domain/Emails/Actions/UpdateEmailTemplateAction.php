<?php

namespace App\Domain\Emails\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Emails\Models\EmailTemplate;
use App\Domain\Emails\Services\EmailService;
use App\Domain\Otp\Enums\OtpAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class UpdateEmailTemplateAction
{
    public function __construct(
        private readonly EmailService $emailService
    ) {}

    public function execute(Collection $collection, string $action, ?string $content): void
    {
        Gate::authorize('manage-schema');

        if ($collection->type !== CollectionType::Auth) {
            throw new AuthorizationException('This collection does not support email templates.');
        }

        $otpAction = OtpAction::tryFrom($action);

        if (! $otpAction) {
            throw new AuthorizationException('Invalid template action.');
        }

        $content = trim(strip_tags($content ?? '')) === ''
            ? $this->emailService->getDefaultTemplate($otpAction->value)
            : $content;

        EmailTemplate::updateOrCreate(
            ['collection_id' => $collection->id, 'action' => $otpAction->value],
            ['content' => $content],
        );
    }
}
