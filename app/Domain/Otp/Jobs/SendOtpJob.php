<?php

namespace App\Domain\Otp\Jobs;

use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Mail\SendOtpMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOtpJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $email,
        public string $otpCode,
        public OtpAction $action,
        public string $collectionId,
        public string $collectionName,
    ) {}

    public function handle(): void
    {
        Mail::to($this->email)->send(
            new SendOtpMail($this->otpCode, $this->action, $this->collectionId, $this->collectionName),
        );
    }
}
