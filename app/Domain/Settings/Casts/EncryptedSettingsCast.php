<?php

namespace App\Domain\Settings\Casts;

use Illuminate\Support\Facades\Crypt;
use Spatie\LaravelSettings\SettingsCasts\SettingsCast;

class EncryptedSettingsCast implements SettingsCast
{
    public function get($payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        try {
            return Crypt::decryptString($payload);
        } catch (\Exception $e) {
            return $payload;
        }
    }

    public function set($payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        return Crypt::encryptString($payload);
    }
}
