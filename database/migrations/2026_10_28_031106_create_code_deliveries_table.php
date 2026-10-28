<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verification codes sent to WhatsApp for Rizq. The code itself is never
     * kept: only who it went to, what became of it, and whether Rizq was
     * told when it could not be delivered. Kept apart from conversations so
     * the team never sees a customer's code in the inbox.
     */
    public function up(): void
    {
        Schema::create('code_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->string('phone', 20)->index();
            $table->string('language', 10);
            $table->string('purpose', 32)->nullable();
            $table->string('status');
            $table->string('external_id')->nullable()->index();
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('rizq_notified_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_deliveries');
    }
};
