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
        Schema::table('route_cache', function (Blueprint $table) {
            $table->integer('duration_seconds')->nullable()->after('total_distance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('route_cache', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
