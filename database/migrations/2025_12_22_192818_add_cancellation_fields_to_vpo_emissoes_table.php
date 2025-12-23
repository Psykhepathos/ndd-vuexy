<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adiciona campos para suportar cancelamento de VPO na NDD Cargo:
     * - cancelled_at: timestamp do cancelamento
     * - cancellation_reason: motivo do cancelamento (1-500 chars)
     * - ndd_cancellation_request: XML de request do cancelamento
     * - ndd_cancellation_response: resposta do cancelamento (JSON)
     */
    public function up(): void
    {
        Schema::table('vpo_emissoes', function (Blueprint $table) {
            // Timestamp do cancelamento
            $table->timestamp('cancelled_at')->nullable()->after('failed_at');

            // Motivo do cancelamento (obrigatório pela NDD Cargo, max 500 chars)
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');

            // XML de request do cancelamento (assinado)
            $table->text('ndd_cancellation_request')->nullable()->after('cancellation_reason');

            // Resposta do cancelamento (JSON com protocolo, raw response, etc)
            $table->json('ndd_cancellation_response')->nullable()->after('ndd_cancellation_request');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vpo_emissoes', function (Blueprint $table) {
            $table->dropColumn([
                'cancelled_at',
                'cancellation_reason',
                'ndd_cancellation_request',
                'ndd_cancellation_response'
            ]);
        });
    }
};
