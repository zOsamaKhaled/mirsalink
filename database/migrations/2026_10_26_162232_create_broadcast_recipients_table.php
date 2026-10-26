<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who a campaign goes to, with the values their variables take, and
     * what happened: queued, left out (and why), sent, or refused. Delivery
     * and reading are read from the message itself.
     */
    public function up(): void
    {
        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->string('name')->nullable();
            $table->json('params')->nullable();
            $table->string('status')->default('queued');
            $table->string('skip_reason')->nullable();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['broadcast_id', 'status']);
            $table->index(['contact_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
    }
};
