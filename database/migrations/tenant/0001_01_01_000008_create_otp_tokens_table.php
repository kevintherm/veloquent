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
        Schema::create('otp_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('collection_id');
            $table->string('record_id');
            $table->string('token_hash')->unique();
            $table->string('action');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'action']);
            $table->index(['collection_id', 'record_id', 'action', 'expires_at']);
            $table->index(['expires_at', 'used_at']);
        });

        Collection::createQuietly([
            'name' => 'otps',
            'table_name' => 'otp_tokens',
            'is_system' => true,
            'fields' => [
                [
                    'name' => 'collection_id',
                    'type' => CollectionFieldType::Text,
                ],
                [
                    'name' => 'record_id',
                    'type' => CollectionFieldType::Text,
                ],
                [
                    'name' => 'token_hash',
                    'type' => CollectionFieldType::Text,
                ],
                [
                    'name' => 'action',
                    'type' => CollectionFieldType::Text,
                ],
                [
                    'name' => 'expires_at',
                    'type' => CollectionFieldType::Datetime,
                ],
                [
                    'name' => 'used_at',
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
        Schema::dropIfExists('otp_tokens');
        Collection::where('name', 'otps')->delete();
    }
};
