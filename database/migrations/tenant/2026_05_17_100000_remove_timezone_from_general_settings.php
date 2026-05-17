<?php

use Veloquent\Core\Support\Settings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('general.timezone');
    }
};
