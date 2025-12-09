<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpo_transportadores_cache', function (Blueprint $table) {
            $table->boolean('editado_manualmente')->default(false)->after('total_usos');
            $table->timestamp('data_edicao_manual')->nullable()->after('editado_manualmente');
        });
    }

    public function down(): void
    {
        Schema::table('vpo_transportadores_cache', function (Blueprint $table) {
            $table->dropColumn(['editado_manualmente', 'data_edicao_manual']);
        });
    }
};
