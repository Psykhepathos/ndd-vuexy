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
        Schema::create('progress_municipios_gps', function (Blueprint $table) {
            $table->id();

            // Chaves do Progress (para relacionar com PUB.municipio)
            $table->integer('cod_mun')->comment('Código do município no Progress');
            $table->integer('cod_est')->comment('Código do estado no Progress');

            // Dados do município (cópia para facilitar queries)
            $table->string('des_mun', 60)->comment('Nome do município');
            $table->string('des_est', 60)->comment('Nome do estado');
            $table->string('sigla_est', 2)->nullable()->comment('UF (ex: SP, RJ)');
            $table->string('cdibge', 7)->nullable()->comment('Código IBGE');

            // Coordenadas GPS
            $table->decimal('latitude', 10, 8)->nullable()->comment('Latitude (-90 a 90)');
            $table->decimal('longitude', 11, 8)->nullable()->comment('Longitude (-180 a 180)');

            // Metadados
            $table->enum('fonte', ['google', 'manual', 'progress', 'ibge'])->default('google')->comment('Origem das coordenadas');
            $table->integer('precisao')->nullable()->comment('Precisão do Google (0-9, quanto maior melhor)');
            $table->timestamp('geocoded_at')->nullable()->comment('Data do último geocoding');

            $table->timestamps();

            // Índices para performance
            $table->unique(['cod_mun', 'cod_est'], 'unique_municipio');
            $table->index('cdibge', 'idx_cdibge');
            $table->index(['des_mun', 'des_est'], 'idx_nome_completo');
            $table->index('geocoded_at', 'idx_geocoded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_municipios_gps');
    }
};
