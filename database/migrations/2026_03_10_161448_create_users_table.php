<?php

use App\Domain\Collections\Enums\CollectionFieldType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        Schema::create('_velo_'.$defaultAuthCollectionName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('email_visibility')->default(true);
            $table->boolean('verified')->default(false);

            $table->timestamps();
        });

        $now = now();
        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'auth',
            'name' => $defaultAuthCollectionName,
            'table_name' => '_velo_'.$defaultAuthCollectionName,
            'description' => 'Default users collection',
            'fields' => json_encode([
                [
                    'name' => 'id',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 26,
                ],
                [
                    'name' => 'name',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => true,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'email',
                    'type' => CollectionFieldType::Email->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 255,
                ],
                [
                    'name' => 'password',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'verified',
                    'type' => CollectionFieldType::Boolean->value,
                    'nullable' => true,
                    'unique' => false,
                    'default' => false,
                ],
                [
                    'name' => 'email_visibility',
                    'type' => CollectionFieldType::Boolean->value,
                    'nullable' => true,
                    'unique' => false,
                    'default' => true,
                ],
                [
                    'name' => 'created_at',
                    'type' => CollectionFieldType::Datetime->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'updated_at',
                    'type' => CollectionFieldType::Datetime->value,
                    'nullable' => false,
                    'unique' => false,
                ],
            ]),
            'api_rules' => json_encode([
                'list' => 'id = @request.auth.id',
                'view' => 'id = @request.auth.id',
                'create' => '',
                'update' => 'id = @request.auth.id',
                'delete' => 'id = @request.auth.id',
            ]),
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
