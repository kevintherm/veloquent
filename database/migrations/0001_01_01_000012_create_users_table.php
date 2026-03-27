<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;
use Illuminate\Database\Migrations\Migration;

/**
 * Users
 *
 * This is a default users collection used by regular users
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultAuthCollectionName = config('velo.default_auth_collection');

        app(CreateCollectionAction::class)->execute([
            'type' => 'auth',
            'name' => $defaultAuthCollectionName,
            'description' => 'Default auth users collection',
            'fields' => [
                [
                    'name' => 'name',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => true,
                    'unique' => false,
                    'length' => 255,
                ],
            ],
            'indexes' => [
                [
                    'type' => 'unique',
                    'columns' => ['email'],
                ],
            ],
            'api_rules' => [
                'list' => 'id = @request.auth.id',
                'view' => 'id = @request.auth.id',
                'create' => '',
                'update' => 'id = @request.auth.id',
                'delete' => 'id = @request.auth.id',
                'manage' => null,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $collection = Collection::where('name', 'users')->first();
        if ($collection) {
            $collection->delete();
        }
    }
};
