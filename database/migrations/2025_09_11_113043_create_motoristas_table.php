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
        Schema::create('motoristas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_progress')->unique(); // Código do sistema Progress
            $table->string('nome');
            $table->string('cpf')->unique();
            $table->string('cnh');
            $table->date('vencimento_cnh')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['ativo', 'inativo', 'suspenso'])->default('ativo');
            $table->json('dados_progress')->nullable(); // Dados adicionais do Progress
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motoristas');
    }
};