<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each entry's meaning as numbers (bge-m3 on Ollama), for finding the
     * entries closest to a customer's question, and which model made them:
     * vectors from different models don't compare.
     */
    public function up(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table) {
            $table->json('embedding')->nullable()->after('active');
            $table->string('embedding_model')->nullable()->after('embedding');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_model']);
        });
    }
};
