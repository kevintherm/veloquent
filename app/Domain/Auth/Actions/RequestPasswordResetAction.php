<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Services\OtpService;
use App\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;

class RequestPasswordResetAction
{
    public function __construct(private OtpService $otpService) {}

    /**
     * Issue a password reset OTP to the user if the email exists in the
     * target collection. Silent success for non-existing addresses.
     */
    public function execute(Collection $collection, array $payload): void
    {
        $email = $payload['email'] ?? null;

        if ($collection->type !== CollectionType::Auth) {
            throw new AuthorizationException('This collection does not support authentication.');
        }

        $user = Record::of($collection)->where('email', $email)->first();

        if ($user) {
            $this->otpService->issue($user, OtpAction::PasswordReset, $collection);
        }
    }
}
