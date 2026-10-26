<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every line in a conversation: what the customer sent, what the team or
     * the assistant replied, and internal notes the customer never sees.
     * Shaped after WhatsApp Cloud API messages, so the simulator and Meta
     * fill the same columns.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('sender');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('media_name')->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            // WhatsApp's own id for the message ("wamid…"), to match statuses
            // and ignore a delivery we have already seen.
            $table->string('external_id')->nullable()->unique();
            $table->string('status')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
