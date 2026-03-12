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
            'description' => 'Superusers collection',
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
                    'nullable' => false,
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
                    'name' => 'token_key',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 64,
                ],
                [
                    'name' => 'password',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
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
            'api_rules' => null,
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'base',
            'is_system' => true,
            'name' => 'password_reset_tokens',
            'description' => 'Password reset tokens collection',
            'fields' => json_encode([
                [
                    'name' => 'email',
                    'type' => CollectionFieldType::Email->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 255,
                ],
                [
                    'name' => 'token',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'created_at',
                    'type' => CollectionFieldType::Datetime->value,
                    'nullable' => true,
                    'unique' => false,
                ],
            ]),
            'api_rules' => null,
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'base',
            'is_system' => true,
            'name' => 'refresh_tokens',
            'description' => 'Refresh tokens collection',
            'fields' => json_encode([
                [
                    'name' => 'id',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 26,
                ],
                [
                    'name' => 'record_id',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'token',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 64,
                ],
                [
                    'name' => 'expires_at',
                    'type' => CollectionFieldType::Datetime->value,
                    'nullable' => false,
                    'unique' => false,
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
            'api_rules' => null,
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'base',
            'is_system' => true,
            'name' => 'cache',
            'description' => 'Cache collection',
            'fields' => json_encode([
                [
                    'name' => 'key',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 255,
                ],
                [
                    'name' => 'value',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'expiration',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => false,
                    'unique' => false,
                ],
            ]),
            'api_rules' => null,
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Cache locks collection
        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'base',
            'is_system' => true,
            'name' => 'cache_locks',
            'description' => 'Cache locks collection',
            'fields' => json_encode([
                [
                    'name' => 'key',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 255,
                ],
                [
                    'name' => 'owner',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'expiration',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => false,
                    'unique' => false,
                ],
            ]),
            'api_rules' => null,
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Jobs collection
        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'base',
            'is_system' => true,
            'name' => 'jobs',
            'description' => 'Jobs collection',
            'fields' => json_encode([
                [
                    'name' => 'id',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 26,
                ],
                [
                    'name' => 'queue',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'payload',
                    'type' => CollectionFieldType::Json->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'attempts',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'reserved_at',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => true,
                    'unique' => false,
                ],
                [
                    'name' => 'available_at',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'created_at',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => false,
                    'unique' => false,
                ],
            ]),
            'api_rules' => null,
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Job batches collection
        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'base',
            'is_system' => true,
            'name' => 'job_batches',
            'description' => 'Job batches collection',
            'fields' => json_encode([
                [
                    'name' => 'id',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 255,
                ],
                [
                    'name' => 'name',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'total_jobs',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'pending_jobs',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'failed_jobs',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'failed_job_ids',
                    'type' => CollectionFieldType::Json->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'options',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => true,
                    'unique' => false,
                ],
                [
                    'name' => 'cancelled_at',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => true,
                    'unique' => false,
                ],
                [
                    'name' => 'created_at',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'finished_at',
                    'type' => CollectionFieldType::Number->value,
                    'nullable' => true,
                    'unique' => false,
                ],
            ]),
            'api_rules' => null,
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Failed jobs collection
        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'base',
            'is_system' => true,
            'name' => 'failed_jobs',
            'description' => 'Failed jobs collection',
            'fields' => json_encode([
                [
                    'name' => 'id',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 26,
                ],
                [
                    'name' => 'uuid',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 255,
                ],
                [
                    'name' => 'connection',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'queue',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'payload',
                    'type' => CollectionFieldType::Json->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'exception',
                    'type' => CollectionFieldType::Json->value,
                    'nullable' => false,
                    'unique' => false,
                ],
                [
                    'name' => 'failed_at',
                    'type' => CollectionFieldType::Datetime->value,
                    'nullable' => false,
                    'unique' => false,
                ],
            ]),
            'api_rules' => null,
            'schema_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Schema jobs collection
        DB::table('collections')->insert([
            'id' => Str::ulid(),
            'type' => 'base',
            'is_system' => true,
            'name' => 'schema_jobs',
            'description' => 'Schema jobs collection',
            'fields' => json_encode([
                [
                    'name' => 'id',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => true,
                    'length' => 26,
                ],
                [
                    'name' => 'collection_id',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 26,
                ],
                [
                    'name' => 'operation',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'table_name',
                    'type' => CollectionFieldType::Text->value,
                    'nullable' => false,
                    'unique' => false,
                    'length' => 255,
                ],
                [
                    'name' => 'started_at',
                    'type' => CollectionFieldType::Datetime->value,
                    'nullable' => false,
                    'unique' => false,
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
        DB::table('collections')->whereIn('name', [
            'superusers',
            'password_reset_tokens',
            'refresh_tokens',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'schema_jobs',
        ])->where('is_system', true)->delete();
    }
};
