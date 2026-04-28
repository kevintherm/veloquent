<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name = 'Veloquent App';

    public string $app_url = 'http://localhost';

    public string $timezone = 'UTC';

    public string $locale = 'en';

    public string $contact_email = 'admin@example.com';

    public bool $lock_schema_change = false;

    public static function group(): string
    {
        return 'general';
    }
}
