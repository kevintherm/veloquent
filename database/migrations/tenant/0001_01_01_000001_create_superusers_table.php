<?php

use App\Domain\Collections\Enums\CollectionFieldType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('superusers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'auth',
            'is_system' => true,
            'name' => 'superusers',
            'table_name' => 'superusers',
            'description' => 'Superusers collection',
            'fields' => json_encode([
                [
                    'id' => bin2hex(random_bytes(4)),
                    'name' => 'id',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 26,
                ],
                [
                    'id' => bin2hex(random_bytes(4)),
                    'name' => 'name',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'id' => bin2hex(random_bytes(4)),
                    'name' => 'email',
                    'type' => CollectionFieldType::Email->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 255,
                ],
                [
                    'id' => bin2hex(random_bytes(4)),
                    'name' => 'password',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'id' => bin2hex(random_bytes(4)),
                    'name' => 'created_at',
                    'type' => CollectionFieldType::Datetime->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'id' => bin2hex(random_bytes(4)),
                    'name' => 'updated_at',
                    'type' => CollectionFieldType::Datetime->value,
                    'nullable' => false,
                    'unique' => false,
                ],
            ]),
            'api_rules' => json_encode([
                'list' => null,
                'view' => null,
                'create' => null,
                'update' => null,
                'delete' => null,
            ]),
            'schema_updated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('superusers');
        DB::table('collections')->where('name', 'superusers')->where('is_system', true)->delete();
    }
};
