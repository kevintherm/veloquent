<?php

namespace Veloquent\Core\Domain\Settings;

use Veloquent\Core\Domain\Settings\Casts\EncryptedSettingsCast;
use Veloquent\Core\Support\Settings\Settings;

class AiSettings extends Settings
{
    public ?string $ai_provider = null;

    public ?string $ai_model = null;

    public ?string $ai_api_key = null;

    public static function casts(): array
    {
        return [
            'ai_api_key' => EncryptedSettingsCast::class,
        ];
    }

    public static function group(): string
    {
        return 'ai';
    }
}
