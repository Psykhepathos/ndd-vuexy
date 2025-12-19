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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module'); // Módulo/Tela (usuarios, transportes, pacotes, etc.)
            $table->string('action'); // Ação (view, create, edit, delete, export, etc.)
            $table->string('name')->unique(); // Identificador único (usuarios.view, transportes.create)
            $table->string('display_name'); // Nome de exibição
            $table->text('description')->nullable();
            $table->string('group')->nullable(); // Agrupamento para UI (Cadastros, Operações, etc.)
            $table->integer('sort_order')->default(0); // Ordem de exibição
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['module', 'action']);
            $table->index('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
