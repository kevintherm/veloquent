<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->char('collection_id', 26);
            $table->string('action');
            $table->longText('content');
            $table->timestamps();

            $table->unique(['collection_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
