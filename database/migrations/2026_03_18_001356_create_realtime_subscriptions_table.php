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
        Schema::create('realtime_subscriptions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('collection_id', 26);
            $table->string('auth_collection');
            $table->char('subscriber_id', 26);
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realtime_subscriptions');
    }
};
