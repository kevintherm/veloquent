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
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('token_key');
            $table->boolean('email_visibility')->default(true);
            $table->boolean('verified')->default(false);

            $table->timestamps();
        });

        $now = now();

        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'auth',
            'name' => 'users',
            'description' => 'Default users collection',
            'fields' => json_encode([
                [
                    'name' => 'id',
                    'type' => CollectionFieldType::Char->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 26,
                ],
                [
                    'name' => 'email',
                    'type' => CollectionFieldType::String->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 255,
                ],
                [
                    'name' => 'password',
                    'type' => CollectionFieldType::String->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'token_key',
                    'type' => CollectionFieldType::String->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'verified',
                    'type' => CollectionFieldType::Boolean->value,
                    'nullable' => false,
                    'unique' => false,
                    'default' => false,
                ],
                [
                    'name' => 'email_visibility',
                    'type' => CollectionFieldType::Boolean->value,
                    'nullable' => false,
                    'unique' => false,
                    'default' => true,
                ],
                [
                    'name' => 'created_at',
                    'type' => CollectionFieldType::Timestamp->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'updated_at',
                    'type' => CollectionFieldType::Timestamp->value,
                    'nullable' => false,
                    'unique' => false,
                ],
            ]),
            'api_rules' => null,
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
