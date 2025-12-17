<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cache de veículos validados no SemParar
     * Permite reutilização de dados e modificação pelo usuário
     */
    public function up(): void
    {
        Schema::create('veiculo_semparar_cache', function (Blueprint $table) {
            $table->id();

            // Identificação do veículo (chave única)
            $table->string('placa', 10)->unique()->comment('Placa do veículo (formato ABC1234 ou ABC1D23)');

            // Dados retornados pelo SemParar
            $table->string('descricao', 100)->nullable()->comment('Descrição do veículo (ex: CAMINHÃO TOCO)');
            $table->integer('eixos')->default(2)->comment('Quantidade de eixos');
            $table->string('proprietario', 150)->nullable()->comment('Nome do proprietário');
            $table->string('tag', 50)->nullable()->comment('Número da TAG SemParar');
            $table->string('status', 20)->default('ATIVO')->comment('Status no SemParar (ATIVO/INATIVO/PENDENTE)');

            // Dados adicionais (podem ser editados pelo usuário)
            $table->string('tipo_veiculo', 50)->nullable()->comment('Tipo (TOCO, TRUCK, CARRETA, BITREM, etc)');
            $table->string('modelo', 100)->nullable()->comment('Modelo do veículo');
            $table->string('marca', 50)->nullable()->comment('Marca do veículo');
            $table->integer('ano_fabricacao')->nullable()->comment('Ano de fabricação');
            $table->string('renavam', 20)->nullable()->comment('Código RENAVAM');
            $table->string('chassi', 30)->nullable()->comment('Número do chassi');

            // Relações opcionais (para vincular a transportador/motorista)
            $table->integer('codtrn')->nullable()->comment('Código do transportador Progress (opcional)');
            $table->integer('codmot')->nullable()->comment('Código do motorista Progress (opcional)');

            // Metadados de controle
            $table->boolean('editado_manualmente')->default(false)->comment('Se foi editado manualmente pelo usuário');
            $table->timestamp('ultima_validacao_semparar')->nullable()->comment('Última validação na API SemParar');
            $table->boolean('dados_semparar_reais')->default(false)->comment('Se os dados vieram da API real ou simulados');

            // Uso e auditoria
            $table->timestamp('ultimo_uso')->nullable()->comment('Última vez que foi usado');
            $table->integer('total_usos')->default(0)->comment('Contador de usos');
            $table->unsignedBigInteger('usuario_criacao_id')->nullable()->comment('Usuário que criou o registro');
            $table->unsignedBigInteger('usuario_atualizacao_id')->nullable()->comment('Último usuário que atualizou');

            $table->timestamps();

            // Índices
            $table->index('codtrn');
            $table->index('codmot');
            $table->index('status');
            $table->index('tipo_veiculo');
            $table->index('ultima_validacao_semparar');
            $table->index(['codtrn', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veiculo_semparar_cache');
    }
};
