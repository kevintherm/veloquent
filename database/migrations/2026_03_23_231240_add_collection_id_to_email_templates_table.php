<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique(['action']);
            $table->char('collection_id', 26)->after('id');
            $table->unique(['collection_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique(['collection_id', 'action']);
            $table->dropColumn('collection_id');
            $table->unique(['action']);
        });
    }
};
