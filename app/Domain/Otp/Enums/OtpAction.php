<?php

namespace App\Domain\Otp\Enums;

enum OtpAction: string
{
    case PasswordReset = 'password_reset';
    case EmailVerification = 'email_verification';
    case EmailChange = 'email_change';

    public function label(): string
    {
        return match ($this) {
            self::PasswordReset => 'Password Reset',
            self::EmailVerification => 'Email Verification',
            self::EmailChange => 'Email Change',
        };
    }
}
