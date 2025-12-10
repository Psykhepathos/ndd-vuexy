<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Torna campos nullable que podem não existir no Progress Database legado
     * Nota: Estes campos são OBRIGATÓRIOS no VPO, mas podem faltar no Progress.
     * O score de qualidade penalizará registros com campos faltantes.
     */
    public function up(): void
    {
        Schema::table('vpo_transportadores_cache', function (Blueprint $table) {
            // Condutor - campos que podem faltar no Progress
            $table->string('condutor_rg', 20)->nullable()->change();
            $table->string('condutor_nome_mae', 100)->nullable()->change();
            $table->date('condutor_data_nascimento')->nullable()->change();

            // Endereço - estado pode faltar
            $table->char('endereco_estado', 2)->nullable()->change();

            // Contato - campos que podem faltar
            $table->string('contato_celular', 11)->nullable()->change();
            $table->string('contato_email', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vpo_transportadores_cache', function (Blueprint $table) {
            // Reverter para NOT NULL
            $table->string('condutor_rg', 20)->nullable(false)->change();
            $table->string('condutor_nome_mae', 100)->nullable(false)->change();
            $table->date('condutor_data_nascimento')->nullable(false)->change();
            $table->char('endereco_estado', 2)->nullable(false)->change();
            $table->string('contato_celular', 11)->nullable(false)->change();
            $table->string('contato_email', 100)->nullable(false)->change();
        });
    }
};
