<?php

namespace App\Domain\Otp\Enums;

enum OtpAction: string
{
    case PasswordReset = 'password_reset';
    case EmailVerification = 'email_verification';

    public function label(): string
    {
        return match ($this) {
            self::PasswordReset => 'Password Reset',
            self::EmailVerification => 'Email Verification',
        };
    }
}
