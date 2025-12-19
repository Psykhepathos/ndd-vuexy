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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // admin, operador, visualizador, etc.
            $table->string('display_name'); // Nome de exibição
            $table->text('description')->nullable();
            $table->string('color')->default('primary'); // Cor do badge
            $table->string('icon')->default('tabler-user'); // Ícone
            $table->boolean('is_system')->default(false); // Se é perfil do sistema (não pode ser excluído)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
