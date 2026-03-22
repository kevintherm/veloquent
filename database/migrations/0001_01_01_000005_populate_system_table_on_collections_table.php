<?php

use App\Domain\Collections\Enums\CollectionFieldType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // Superusers collection
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
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'base',
            'is_system' => true,
            'name' => 'passwordResetTokens',
            'table_name' => 'password_reset_tokens',
            'description' => 'Password reset tokens collection',
            'fields' => json_encode([
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
                    'name' => 'token',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'id' => bin2hex(random_bytes(4)),
                    'name' => 'created_at',
                    'type' => CollectionFieldType::Datetime->value,
                    'nullable' => true,
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
        DB::table('collections')->whereIn('name', [
            'superusers',
            'password_reset_tokens',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'schema_jobs',
        ])->where('is_system', true)->delete();
    }
};
