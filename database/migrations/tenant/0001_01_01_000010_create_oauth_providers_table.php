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
        Schema::create('oauth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('collection_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->boolean('enabled')->default(true);
            $table->string('client_id');
            $table->text('client_secret');
            $table->string('redirect_uri')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamps();

            $table->unique(['collection_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_providers');
    }
};
