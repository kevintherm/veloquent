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
        Schema::table('auth_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('token_hash');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('fingerprint', 64)->nullable()->after('user_agent');
            $table->timestamp('revoked_at')->nullable()->after('fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth_tokens', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'fingerprint', 'revoked_at']);
        });
    }
};
