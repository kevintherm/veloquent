<?php

use Veloquent\Core\Domain\Collections\Actions\CreateCollectionAction;
use Veloquent\Core\Domain\Collections\Models\Collection;
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

        // 2. Create agents system collection
        app(CreateCollectionAction::class)->execute([
            'type' => 'base',
            'is_system' => true,
            'name' => 'agents',
            'description' => 'System collection for chatbot agents',
            'fields' => [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'nullable' => false,
                    'unique' => true,
                ],
                [
                    'name' => 'model',
                    'type' => 'text',
                    'nullable' => true,
                    'unique' => false,
                ],
                [
                    'name' => 'system_prompt',
                    'type' => 'longtext',
                    'nullable' => true,
                    'unique' => false,
                ],
                [
                    'name' => 'tone',
                    'type' => 'text',
                    'nullable' => true,
                    'unique' => false,
                ],
                [
                    'name' => 'length',
                    'type' => 'text',
                    'nullable' => true,
                    'unique' => false,
                ],
                [
                    'name' => 'temperature',
                    'type' => 'number',
                    'nullable' => true,
                    'unique' => false,
                    'allow_decimals' => true,
                ],
                [
                    'name' => 'output_type',
                    'type' => 'select',
                    'nullable' => true,
                    'unique' => false,
                    'default' => 'text',
                    'options' => ['text', 'json'],
                ],
                [
                    'name' => 'schema',
                    'type' => 'json',
                    'nullable' => true,
                    'unique' => false,
                ],
            ],
            'api_rules' => [
                'list' => '@user.id != null',
                'view' => '@user.id != null',
                'create' => '@user.is_superuser = true',
                'update' => '@user.is_superuser = true',
                'delete' => '@user.is_superuser = true',
                'manage' => null,
            ],
        ]);
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

        // 2. Delete collection
        $collection = Collection::where('name', 'agents')->first();
        if ($collection) {
            $collection->delete();
        }
    }
};
