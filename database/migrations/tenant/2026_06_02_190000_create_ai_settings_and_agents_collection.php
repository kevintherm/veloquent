<?php

use Veloquent\Core\Support\Settings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Seed AI Settings
        $this->migrator->add('ai.ai_provider', null);
        $this->migrator->add('ai.ai_model', null);
        $this->migrator->add('ai.ai_api_key', null);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Delete settings
        $this->migrator->deleteIfExists('ai.ai_provider');
        $this->migrator->deleteIfExists('ai.ai_model');
        $this->migrator->deleteIfExists('ai.ai_api_key');
    }
};
