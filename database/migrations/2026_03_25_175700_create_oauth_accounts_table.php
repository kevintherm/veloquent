<?php

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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_accounts');
    }
};
