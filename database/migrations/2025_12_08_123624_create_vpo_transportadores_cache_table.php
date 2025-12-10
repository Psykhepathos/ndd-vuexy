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
        Schema::create('vpo_transportadores_cache', function (Blueprint $table) {
            $table->id();

            // Chaves de identificação (Progress)
            $table->integer('codtrn')->unique()->comment('Código transportador Progress');
            $table->integer('codmot')->nullable()->comment('Código motorista Progress (apenas empresas)');
            $table->string('numpla', 10)->nullable()->comment('Placa do veículo');
            $table->boolean('flgautonomo')->comment('Flag autônomo (true) ou empresa (false)');

            // === 19 CAMPOS VPO (NDD Cargo) ===

            // Grupo 1: Identificação (4 campos) - todos nullable para dados incompletos
            $table->string('cpf_cnpj', 14)->nullable()->comment('CPF do motorista ou autônomo (11 dígitos)');
            $table->string('antt_rntrc', 20)->nullable()->comment('Código RNTRC');
            $table->string('antt_nome', 100)->nullable()->comment('Nome do motorista/autônomo');
            $table->date('antt_validade')->nullable()->comment('Data de validade do RNTRC');
            $table->string('antt_status', 20)->default('Ativo')->comment('Status RNTRC (Ativo/Suspenso/Cancelado)');

            // Grupo 2: Veículo (3 campos) - todos nullable
            $table->string('placa', 10)->nullable()->comment('Placa veículo (formato Mercosul)');
            $table->string('veiculo_tipo', 50)->nullable()->comment('Tipo do veículo (TOCO, TRUCK, etc)');
            $table->string('veiculo_modelo', 100)->nullable()->comment('Modelo do veículo');

            // Grupo 3: Condutor (4 campos) - todos nullable
            $table->string('condutor_rg', 20)->nullable()->comment('RG do condutor');
            $table->string('condutor_nome', 100)->nullable()->comment('Nome completo do condutor');
            $table->char('condutor_sexo', 1)->default('M')->comment('Sexo (M/F)');
            $table->string('condutor_nome_mae', 100)->nullable()->comment('Nome da mãe');
            $table->date('condutor_data_nascimento')->nullable()->comment('Data de nascimento');

            // Grupo 4: Endereço (4 campos) - todos nullable
            $table->string('endereco_rua', 150)->nullable()->comment('Logradouro completo');
            $table->string('endereco_bairro', 100)->nullable()->comment('Bairro');
            $table->string('endereco_cidade', 100)->nullable()->comment('Município');
            $table->char('endereco_estado', 2)->nullable()->comment('UF');

            // Grupo 5: Contato (2 campos) - todos nullable
            $table->string('contato_celular', 20)->nullable()->comment('Celular (11 dígitos: DDD + número)');
            $table->string('contato_email', 100)->nullable()->comment('E-mail');

            // === METADADOS DE CONTROLE ===

            // Fontes de dados
            $table->json('fontes_dados')->nullable()->comment('Rastreamento de onde vieram os dados');

            // Sincronização
            $table->timestamp('ultima_sync_progress')->nullable()->comment('Última sincronização com Progress');
            $table->timestamp('ultima_sync_antt')->nullable()->comment('Última consulta à ANTT');
            $table->string('antt_fonte', 50)->nullable()->comment('Fonte ANTT (dados_abertos/api_comercial/fallback)');

            // Qualidade dos dados
            $table->integer('score_qualidade')->default(0)->comment('Score 0-100 de qualidade dos dados');
            $table->json('campos_faltantes')->nullable()->comment('Lista de campos sem dados');
            $table->json('avisos')->nullable()->comment('Avisos de validação');

            // Uso
            $table->timestamp('ultimo_uso')->nullable()->comment('Última vez que foi usado em requisição VPO');
            $table->integer('total_usos')->default(0)->comment('Contador de usos');

            $table->timestamps();

            // Índices
            $table->index('cpf_cnpj');
            $table->index('antt_rntrc');
            $table->index('placa');
            $table->index('antt_status');
            $table->index(['flgautonomo', 'antt_status']);
            $table->index('ultima_sync_progress');
            $table->index('ultima_sync_antt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vpo_transportadores_cache');
    }
};
