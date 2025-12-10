<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabela para rastrear emissões de Vale Pedágio Obrigatório (VPO)
     * via NDD Cargo API com fluxo assíncrono (UUID-based polling).
     */
    public function up(): void
    {
        Schema::create('vpo_emissoes', function (Blueprint $table) {
            $table->id();

            // === IDENTIFICAÇÃO ===
            $table->string('uuid', 100)->unique()->comment('UUID retornado pela NDD Cargo');
            $table->integer('codpac')->comment('Código do pacote Progress');
            $table->integer('codtrn')->comment('Código do transportador Progress');
            $table->integer('codmot')->nullable()->comment('Código do motorista Progress (apenas empresas)');

            // === ROTA ===
            $table->integer('rota_id')->comment('ID da rota semPararRot');
            $table->string('rota_nome', 150)->comment('Nome da rota');
            $table->json('waypoints')->comment('Array de coordenadas [lat, lon] incluindo primeira e última entrega');
            $table->integer('total_waypoints')->comment('Quantidade total de waypoints enviados');

            // === DADOS VPO (19 campos) ===
            $table->json('vpo_data')->comment('Dados VPO completos (19 campos) enviados para NDD Cargo');
            $table->json('fontes_dados')->nullable()->comment('Rastreamento de fontes (Progress/ANTT/cache)');
            $table->integer('score_qualidade')->default(0)->comment('Score de qualidade dos dados VPO (0-100)');

            // === STATUS DO PROCESSO ===
            $table->enum('status', [
                'pending',      // Aguardando envio à NDD Cargo
                'processing',   // Enviado, aguardando processamento
                'completed',    // Processamento concluído com sucesso
                'failed',       // Falha no processamento
                'cancelled'     // Cancelado pelo usuário
            ])->default('pending')->comment('Status do processo de emissão');

            // === REQUEST/RESPONSE NDD CARGO ===
            $table->text('ndd_request_xml')->nullable()->comment('XML completo enviado à NDD Cargo');
            $table->json('ndd_response')->nullable()->comment('Response JSON da NDD Cargo');
            $table->text('error_message')->nullable()->comment('Mensagem de erro (se houver)');
            $table->string('error_code', 50)->nullable()->comment('Código de erro da NDD Cargo');

            // === RESULTADOS ===
            $table->json('pracas_pedagio')->nullable()->comment('Lista de praças de pedágio no trajeto');
            $table->integer('total_pracas')->default(0)->comment('Quantidade de praças encontradas');
            $table->decimal('custo_total', 10, 2)->nullable()->comment('Custo total estimado do Vale Pedágio');
            $table->decimal('distancia_km', 10, 2)->nullable()->comment('Distância total calculada (km)');
            $table->integer('tempo_minutos')->nullable()->comment('Tempo estimado de viagem (minutos)');

            // === POLLING CONTROL ===
            $table->integer('tentativas_polling')->default(0)->comment('Contador de tentativas de consulta');
            $table->timestamp('requested_at')->nullable()->comment('Timestamp do envio inicial à NDD Cargo');
            $table->timestamp('polled_at')->nullable()->comment('Timestamp da última consulta ao UUID');
            $table->timestamp('completed_at')->nullable()->comment('Timestamp da conclusão do processamento');
            $table->timestamp('failed_at')->nullable()->comment('Timestamp da falha (se houver)');

            // === METADADOS ===
            $table->unsignedBigInteger('usuario_id')->nullable()->comment('ID do usuário que iniciou a emissão');
            $table->string('ip_address', 45)->nullable()->comment('IP do cliente');
            $table->string('user_agent', 255)->nullable()->comment('User agent do navegador');

            $table->timestamps();

            // === ÍNDICES ===
            $table->index('uuid');
            $table->index('codpac');
            $table->index('codtrn');
            $table->index('rota_id');
            $table->index('status');
            $table->index(['status', 'tentativas_polling']); // Para polling automático
            $table->index('requested_at');
            $table->index('completed_at');
            $table->index(['codpac', 'status']); // Buscar emissões de um pacote

            // === FOREIGN KEYS ===
            $table->foreign('usuario_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vpo_emissoes');
    }
};
