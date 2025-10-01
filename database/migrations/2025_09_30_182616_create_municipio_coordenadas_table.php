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
        Schema::create('municipio_coordenadas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ibge', 7)->unique()->comment('Código IBGE do município (7 dígitos)');
            $table->string('nome_municipio', 100)->comment('Nome do município');
            $table->string('uf', 2)->comment('Sigla do estado');
            $table->decimal('latitude', 10, 8)->comment('Latitude do município');
            $table->decimal('longitude', 11, 8)->comment('Longitude do município');
            $table->string('fonte', 50)->default('google_geocoding')->comment('Fonte das coordenadas (google_geocoding, manual, ibge_api)');
            $table->timestamps();

            $table->index(['codigo_ibge', 'uf']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipio_coordenadas');
    }
};
