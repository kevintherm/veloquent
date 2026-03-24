<?php

namespace App\Domain\Emails\Services;

use App\Domain\Collections\Models\Collection;
use App\Domain\Emails\Mail\TemplateMail;
use App\Domain\Emails\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function send(string $to, string $action, Collection $collection, array $data): void
    {
        $html = $this->render($action, $collection, $data);
        $subject = $data['subject'] ?? str($action)->replace('_', ' ')->title().' — Verification Code';

        Mail::to($to)->send(new TemplateMail($subject, $html));
    }

    public function render(string $action, Collection $collection, array $data): string
    {
        $template = EmailTemplate::where('collection_id', $collection->id)
            ->where('action', $action)
            ->first();

        $content = $template?->content ?? $this->getDefaultTemplate($action);

        $rendered = $this->replacePlaceholders($content, array_merge($data, [
            'app_name' => config('app.name'),
            'collection_name' => $collection->name,
        ]));

        return $this->wrapWithLayout($rendered);
    }

    private function replacePlaceholders(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $content = str_replace("{{ $key }}", (string) $value, $content);
                $content = str_replace("{{$key}}", (string) $value, $content);
            }
        }

        return $content;
    }

    private function wrapWithLayout(string $content): string
    {
        if (str_contains($content, '<html') && str_contains($content, '<body')) {
            return $content;
        }

        $appName = config('app.name');

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 32px; color: #1a1a1a; background-color: #f5f5f5;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
        $content
        <hr style="border: none; border-top: 1px solid #eee; margin: 32px 0 16px 0;">
        <p style="font-size: 12px; color: #aaa; margin: 0;">$appName</p>
    </div>
</body>
</html>
HTML;
    }

    public function getDefaultTemplate(string $action): string
    {
        $actionLabel = str($action)->replace('_', ' ')->title();
        
        $bodyText = match ($action) {
            'password_reset' => 'Use the code below to reset your password.',
            'email_verification' => 'Use the code below to verify your email address.',
            default => 'Use the verification code below.',
        };

        $footerNote = match ($action) {
            'password_reset' => 'If you did not request a password reset, you can safely ignore this email.',
            'email_verification' => 'If you did not request this, you can safely ignore this email.',
            default => 'If you did not request this code, please ignore this email.',
        };

        return <<<HTML
<h2 style="margin: 0 0 8px 0; font-size: 22px; color: #111;">$actionLabel</h2>
<p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">$bodyText</p>
<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 24px;">
    <span style="font-size: 48px; font-weight: 900; letter-spacing: 10px; color: #111;">{{ otp_code }}</span>
</div>
<p style="font-size: 14px; color: #666; margin: 0 0 8px 0;">This code will expire in a few minutes.</p>
<p style="font-size: 14px; color: #666; margin: 0;">$footerNote</p>
HTML;
    }
}
