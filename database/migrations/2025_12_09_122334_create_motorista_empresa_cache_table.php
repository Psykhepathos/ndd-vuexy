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
        Schema::create('motorista_empresa_cache', function (Blueprint $table) {
            $table->id();

            // Chaves do Progress
            $table->integer('codtrn')->index();          // FK para transporte
            $table->integer('codmot')->index();          // FK para trnmot

            // Dados do Progress (cache/espelho)
            $table->string('nommot', 100)->nullable();   // Nome do motorista
            $table->string('numrg', 30)->nullable();     // RG
            $table->string('nompai', 100)->nullable();   // Nome do pai
            $table->string('nommae', 100)->nullable();   // Nome da mãe

            // Dados completados pelo usuário (VPO)
            $table->string('cpf', 11)->nullable();       // CPF do motorista
            $table->string('rntrc', 20)->nullable();     // RNTRC do motorista
            $table->date('data_nascimento')->nullable(); // Data de nascimento
            $table->string('cnh', 20)->nullable();       // Número CNH
            $table->string('categoria_cnh', 5)->nullable(); // Categoria CNH (A, B, C, D, E)
            $table->date('validade_cnh')->nullable();    // Validade CNH

            // Endereço do motorista
            $table->string('endereco_logradouro', 200)->nullable();
            $table->string('endereco_numero', 20)->nullable();
            $table->string('endereco_bairro', 100)->nullable();
            $table->string('endereco_cidade', 100)->nullable();
            $table->string('endereco_uf', 2)->nullable();
            $table->string('endereco_cep', 8)->nullable();

            // Controle
            $table->boolean('dados_completos')->default(false); // Flag se todos os campos VPO estão OK
            $table->boolean('sincronizado_progress')->default(false); // Flag se já foi para o Progress

            // Metadados
            $table->unsignedBigInteger('created_by')->nullable(); // user_id que cadastrou
            $table->unsignedBigInteger('updated_by')->nullable(); // user_id que atualizou
            $table->timestamps();

            // Índices
            $table->unique(['codtrn', 'codmot'], 'unique_transportador_motorista');
            $table->index('cpf');
            $table->index('rntrc');
            $table->index('dados_completos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motorista_empresa_cache');
    }
};
