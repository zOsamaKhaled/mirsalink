<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which template a message was sent from and the values its variables
     * took; the body keeps the text as the customer read it.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->json('template_params')->nullable()->after('template_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn('template_params');
        });
    }
};
