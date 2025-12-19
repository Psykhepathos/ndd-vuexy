<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('setup_token', 64)->nullable()->after('password_changed_at');
            $table->timestamp('setup_token_expires_at')->nullable()->after('setup_token');

            $table->index('setup_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['setup_token']);
            $table->dropColumn(['setup_token', 'setup_token_expires_at']);
        });
    }
};
