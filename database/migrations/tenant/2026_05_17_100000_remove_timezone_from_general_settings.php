<?php

use Spatie\LaravelSettings\Exceptions\SettingDoesNotExist;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        try {
            $this->migrator->delete('general.timezone');
        } catch (SettingDoesNotExist $e) {
            // Setting already removed or never existed
        }
    }
};
