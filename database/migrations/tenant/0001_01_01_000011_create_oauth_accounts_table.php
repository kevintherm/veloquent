<?php

use App\Domain\Collections\Enums\CollectionFieldType;
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
        Schema::create('oauth_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('provider');
            $table->string('provider_user_id');
            $table->foreignUlid('collection_id')->constrained()->cascadeOnDelete();
            $table->char('record_id', 26);
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id', 'collection_id']);
            $table->index(['collection_id', 'record_id']);
        });

        Collection::createQuietly([
            'name' => 'oauth',
            'table_name' => 'oauth_accounts',
            'type' => 'base',
            'is_system' => true,
            'fields' => [
                [
                    'name' => 'id',
                    'type' => CollectionFieldType::Text,
                ],
                [
                    'name' => 'provider',
                    'type' => CollectionFieldType::Text,
                ],
                [
                    'name' => 'provider_user_id',
                    'type' => CollectionFieldType::Text,
                ],
                [
                    'name' => 'collection_id',
                    'type' => CollectionFieldType::Text,
                ],
                [
                    'name' => 'record_id',
                    'type' => CollectionFieldType::Text,
                ],
                [
                    'name' => 'email',
                    'type' => CollectionFieldType::Email,
                ],
                [
                    'name' => 'created_at',
                    'type' => CollectionFieldType::Datetime,
                ],
                [
                    'name' => 'updated_at',
                    'type' => CollectionFieldType::Datetime,
                ],
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_accounts');
        Collection::where('name', 'oauth')->deleteQuietly();
    }
};
