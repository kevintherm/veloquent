<?php

namespace Veloquent\Core\Domain\Settings;

use Veloquent\Core\Domain\Settings\Casts\EncryptedSettingsCast;
use Veloquent\Core\Support\Settings\Settings;

class EmailSettings extends Settings
{
    public string $mail_driver = 'smtp';

    public string $mail_host = '127.0.0.1';

    public int $mail_port = 1025;

    public string $mail_encryption = 'tls';

    public ?string $mail_username = null;

    public ?string $mail_password = null;

    public string $mail_from_address = 'noreply@example.com';

    public string $mail_from_name = 'Veloquent';

    public static function casts(): array
    {
        return [
            'mail_password' => EncryptedSettingsCast::class,
        ];
    }

    protected function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->defaults['mail_driver'] = config('mail.default') ?: 'smtp';
        $this->defaults['mail_host'] = config('mail.mailers.smtp.host') ?: '127.0.0.1';
        $this->defaults['mail_port'] = (int) (config('mail.mailers.smtp.port') ?: 1025);
        $this->defaults['mail_encryption'] = config('mail.mailers.smtp.encryption') ?: 'tls';
        $this->defaults['mail_username'] = config('mail.mailers.smtp.username');
        $this->defaults['mail_password'] = config('mail.mailers.smtp.password');
        $this->defaults['mail_from_address'] = config('mail.from.address') ?: 'noreply@example.com';
        $this->defaults['mail_from_name'] = config('mail.from.name') ?: 'Veloquent';

        parent::ensureLoaded();
    }

    public static function group(): string
    {
        return 'email';
    }
}
