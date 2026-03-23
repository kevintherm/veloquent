<?php

namespace App\Domain\Otp\Mail;

use App\Domain\Otp\Enums\OtpAction;
use App\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otpCode,
        public OtpAction $action,
        public string $collectionId,
        public string $collectionName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->action->label().' — Verification Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $template = EmailTemplate::where('collection_id', $this->collectionId)
            ->where('action', $this->action->value)
            ->first();

        $snippet = $template?->content ?? $this->action->defaultTemplate();

        $html = $this->wrapWithLayout($snippet);

        return str_replace(
            ['{{ otp_code }}', '{{ action_label }}', '{{ collection_name }}', '{{ app_name }}'],
            [$this->otpCode, $this->action->label(), $this->collectionName, config('app.name')],
            $html,
        );
    }

    private function wrapWithLayout(string $snippet): string
    {
        // If the snippet already contains a full html structure, return as is
        if (str_contains($snippet, '<html') && str_contains($snippet, '<body')) {
            return $snippet;
        }

        $appName = config('app.name');

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 32px; color: #1a1a1a; background-color: #f5f5f5;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
        $snippet
        <hr style="border: none; border-top: 1px solid #eee; margin: 32px 0 16px 0;">
        <p style="font-size: 12px; color: #aaa; margin: 0;">$appName</p>
    </div>
</body>
</html>
HTML;
    }
}
