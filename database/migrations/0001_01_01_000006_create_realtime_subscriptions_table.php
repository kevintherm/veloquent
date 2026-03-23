<?php

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
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
        Schema::create('realtime_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('collection_id');
            $table->string('auth_collection');
            $table->ulid('subscriber_id');
            $table->string('channel');
            $table->text('filter')->nullable();
            $table->timestamp('expired_at');
            $table->timestamps();

            $table->index('collection_id', 'rt_subs_collection_idx');
            $table->index('expired_at', 'rt_subs_expired_at_idx');
            $table->index(['collection_id', 'expired_at'], 'rt_subs_collection_expired_idx');
            $table->unique(
                ['collection_id', 'auth_collection', 'subscriber_id'],
                'rt_subs_collection_auth_sub_uq'
            );
        });

        Collection::createQuietly([
            'name' => 'realtimeSubscriptions',
            'is_system' => true,
            'type' => CollectionType::Base,
            'description' => 'Stores realtime subscriptions for the system.',
            'table_name' => 'realtime_subscriptions',
            'fields' => [
                [
                    'name' => 'id',
                    'type' => CollectionFieldType::Text,
                    'nullable' => false,
                    'unique' => true,
                ],
                [
                    'name' => 'collection_name',
                    'type' => CollectionFieldType::Text,
                    'nullable' => false,
                ],
                [
                    'name' => 'auth_collection',
                    'type' => CollectionFieldType::Text,
                    'nullable' => false,
                ],
                [
                    'name' => 'subscriber_id',
                    'type' => CollectionFieldType::Text,
                    'nullable' => false,
                ],
                [
                    'name' => 'channel',
                    'type' => CollectionFieldType::Text,
                    'nullable' => false,
                ],
                [
                    'name' => 'filter',
                    'type' => CollectionFieldType::Text,
                    'nullable' => true,
                ],
                [
                    'name' => 'expires_at',
                    'type' => CollectionFieldType::Datetime,
                    'nullable' => false,
                ],
                [
                    'name' => 'created_at',
                    'type' => CollectionFieldType::Datetime,
                    'nullable' => true,
                ],
                [
                    'name' => 'updated_at',
                    'type' => CollectionFieldType::Datetime,
                    'nullable' => true,
                ],
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realtime_subscriptions');

        Collection::where('name', 'realtimeSubscriptions')->deleteQuietly();
    }
};
