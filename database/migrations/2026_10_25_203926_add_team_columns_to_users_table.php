<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every user is a team member: a role, whether they take new
     * conversations, and the trail of their invitation and deactivation.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('agent')->index()->after('email');
            $table->string('availability')->default('available')->after('role');
            $table->timestamp('invited_at')->nullable()->after('availability');
            $table->foreignId('invited_by')->nullable()->after('invited_at')->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable()->index()->after('invited_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn(['role', 'availability', 'invited_at', 'deactivated_at']);
        });
    }
};
