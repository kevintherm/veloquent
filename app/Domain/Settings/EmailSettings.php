<?php

namespace App\Domain\Settings;

use App\Domain\Settings\Casts\EncryptedSettingsCast;
use Spatie\LaravelSettings\Settings;

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

    public static function group(): string
    {
        return 'email';
    }
}
