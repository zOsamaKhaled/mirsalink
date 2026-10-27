<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every turn the assistant takes on a customer message: what it made of
     * it, what it did (or, in silent mode, would have done), the reply it
     * wrote, which model, how long it took, and whether the model failed.
     */
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode');
            $table->string('driver');
            $table->string('model')->nullable();
            $table->string('status');
            $table->string('intent')->nullable();
            $table->json('tags')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('decision')->nullable();
            $table->text('reply')->nullable();
            $table->json('sources')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['mode', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
