<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every signed notification from Meta, kept as it arrived before it is
     * read, so nothing is lost when reading fails: it is read again until it
     * succeeds, and the raw payload stays for checking what Meta sent.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['processed_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
