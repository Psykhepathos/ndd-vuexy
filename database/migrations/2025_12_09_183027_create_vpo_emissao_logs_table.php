<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration para criar tabela de logs de auditoria de emissões VPO
 *
 * Armazena todos os dados de cada emissão VPO para auditoria:
 * - Dados do pacote, transportador, motorista, veículo
 * - Rota e praças de pedágio calculadas
 * - Requisições/respostas NDD Cargo
 * - Status e timestamps
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpo_emissao_logs', function (Blueprint $table) {
            $table->id();

            // UUID da emissão (gerado localmente ou retornado pela NDD)
            $table->uuid('uuid')->unique();

            // Status da emissão
            $table->enum('status', [
                'iniciado',      // Emissão iniciada
                'calculando',    // Calculando praças
                'aguardando',    // Aguardando resposta NDD
                'sucesso',       // Emissão concluída com sucesso
                'erro',          // Erro na emissão
                'cancelado',     // Emissão cancelada
            ])->default('iniciado');

            // Dados do pacote
            $table->integer('codpac')->nullable()->index();
            $table->string('pacote_situacao', 50)->nullable();

            // Dados do transportador
            $table->integer('codtrn')->nullable()->index();
            $table->string('transportador_nome', 200)->nullable();
            $table->string('transportador_cpf_cnpj', 20)->nullable();
            $table->boolean('transportador_autonomo')->default(false);
            $table->string('transportador_rntrc', 20)->nullable();

            // Dados do motorista (se empresa)
            $table->integer('codmot')->nullable();
            $table->string('motorista_nome', 200)->nullable();
            $table->string('motorista_cpf', 20)->nullable();

            // Dados do veículo
            $table->string('veiculo_placa', 10)->nullable()->index();
            $table->string('veiculo_modelo', 100)->nullable();
            $table->integer('veiculo_eixos')->nullable();
            $table->integer('categoria_pedagio')->nullable();

            // Dados da rota
            $table->integer('rota_id')->nullable();
            $table->string('rota_nome', 200)->nullable();
            $table->integer('rota_municipios_count')->nullable();
            $table->json('rota_municipios')->nullable(); // Array de municípios

            // Praças de pedágio calculadas
            $table->integer('pracas_count')->default(0);
            $table->json('pracas_pedagio')->nullable(); // Array de praças
            $table->decimal('valor_total_pedagios', 12, 2)->default(0);
            $table->decimal('distancia_km', 10, 2)->nullable();
            $table->integer('tempo_estimado_min')->nullable();

            // Período da viagem
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();

            // Requisição NDD Cargo - Roteirizador
            $table->string('roteirizador_guid', 50)->nullable();
            $table->json('roteirizador_request')->nullable();
            $table->json('roteirizador_response')->nullable();
            $table->timestamp('roteirizador_enviado_em')->nullable();
            $table->timestamp('roteirizador_respondido_em')->nullable();

            // Requisição NDD Cargo - Emissão VPO
            $table->string('emissao_guid', 50)->nullable();
            $table->json('emissao_request')->nullable();
            $table->json('emissao_response')->nullable();
            $table->timestamp('emissao_enviada_em')->nullable();
            $table->timestamp('emissao_respondida_em')->nullable();

            // Resultado final
            $table->string('ndd_codigo_retorno', 50)->nullable();
            $table->text('ndd_mensagem_retorno')->nullable();
            $table->string('ndd_protocolo', 100)->nullable();

            // Mensagens de erro (se houver)
            $table->text('erro_mensagem')->nullable();
            $table->text('erro_detalhes')->nullable();

            // Auditoria
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 100)->nullable();
            $table->string('user_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            // Timestamps
            $table->timestamps();
            $table->timestamp('concluido_em')->nullable();

            // Índices para buscas
            $table->index(['status', 'created_at']);
            $table->index(['codtrn', 'created_at']);
            $table->index(['veiculo_placa', 'created_at']);
            $table->index('roteirizador_guid');
            $table->index('emissao_guid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpo_emissao_logs');
    }
};
