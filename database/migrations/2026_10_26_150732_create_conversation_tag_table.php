<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A conversation's tags, and who put each one there: a team member, or
     * the assistant (phase 8) when tagged_by is empty and by_bot is true.
     */
    public function up(): void
    {
        Schema::create('conversation_tag', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('by_bot')->default(false);
            $table->timestamps();

            $table->primary(['conversation_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_tag');
    }
};
