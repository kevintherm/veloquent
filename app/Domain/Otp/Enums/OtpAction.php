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

    public function defaultTemplate(): string
    {
        $bodyText = match ($this) {
            self::PasswordReset => 'Use the code below to reset your password.',
            self::EmailVerification => 'Use the code below to verify your email address.',
        };
        $footerNote = match ($this) {
            self::PasswordReset => 'If you did not request a password reset, you can safely ignore this email.',
            self::EmailVerification => 'If you did not request this, you can safely ignore this email.',
        };

        return <<<HTML
<h2 style="margin: 0 0 8px 0; font-size: 22px; color: #111;">{{ action_label }}</h2>
<p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">$bodyText</p>
<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 24px;">
    <span style="font-size: 36px; font-weight: 700; letter-spacing: 10px; color: #111;">{{ otp_code }}</span>
</div>
<p style="font-size: 14px; color: #666; margin: 0 0 8px 0;">This code will expire in a few minutes.</p>
<p style="font-size: 14px; color: #666; margin: 0;">$footerNote</p>
HTML;
    }
}
