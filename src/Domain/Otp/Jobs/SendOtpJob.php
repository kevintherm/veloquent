<?php

namespace Veloquent\Core\Domain\Otp\Jobs;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Emails\Services\EmailService;
use Veloquent\Core\Domain\Otp\Enums\OtpAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOtpJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $email,
        public string $otpCode,
        public OtpAction $action,
        public Collection $collection,
    ) {}

    public function handle(EmailService $emailService): void
    {
        $emailService->send(
            $this->email,
            $this->action->value,
            $this->collection,
            [
                'otp_code' => $this->otpCode,
                'action_label' => $this->action->label(),
            ]
        );
    }
}
