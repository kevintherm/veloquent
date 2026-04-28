<?php

namespace App\Domain\Settings;

use App\Domain\Settings\Casts\EncryptedSettingsCast;
use Spatie\LaravelSettings\Settings;

class StorageSettings extends Settings
{
    public string $storage_driver = 'local'; // local, s3

    public string $s3_key = '';

    public string $s3_secret = '';

    public string $s3_region = '';

    public string $s3_bucket = '';

    public string $s3_endpoint = '';

    public static function group(): string
    {
        return 'storage';
    }

    public static function casts(): array
    {
        return [
            's3_key' => EncryptedSettingsCast::class,
            's3_secret' => EncryptedSettingsCast::class,
        ];
    }
}
