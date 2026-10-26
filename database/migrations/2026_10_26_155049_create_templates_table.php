<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WhatsApp message templates, the only messages allowed after the
     * 24-hour window. Written here as drafts, approved by Meta (the
     * simulator until phase 9), and known to Meta by name and language.
     */
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->string('language', 10);
            $table->string('category');
            $table->string('header_type')->default('none');
            $table->string('header_text', 60)->nullable();
            $table->string('header_example')->nullable();
            $table->text('body');
            $table->json('body_examples')->nullable();
            $table->string('footer', 60)->nullable();
            $table->json('buttons')->nullable();
            $table->string('status')->default('draft');
            $table->string('rejection_reason')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['name', 'language']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
