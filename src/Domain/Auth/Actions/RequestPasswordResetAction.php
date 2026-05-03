<?php

namespace Veloquent\Core\Domain\Auth\Actions;

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Otp\Enums\OtpAction;
use Veloquent\Core\Domain\Otp\Services\OtpService;
use Veloquent\Core\Domain\Records\Models\Record;
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
