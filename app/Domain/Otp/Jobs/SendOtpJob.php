<?php

namespace App\Domain\Otp\Jobs;

use App\Domain\Collections\Models\Collection;
use App\Domain\Emails\Services\EmailService;
use App\Domain\Otp\Enums\OtpAction;
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
