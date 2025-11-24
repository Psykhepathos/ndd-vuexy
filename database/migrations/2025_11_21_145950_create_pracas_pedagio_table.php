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
        Schema::create('pracas_pedagio', function (Blueprint $table) {
            $table->id();

            // Dados da praça
            $table->string('concessionaria', 100);
            $table->string('praca', 100);  // Nome da praça
            $table->string('rodovia', 20);  // BR-XXX
            $table->string('uf', 2);
            $table->decimal('km', 8, 3);  // Quilômetro (999999.999)
            $table->string('municipio', 100);

            // Classificação
            $table->integer('ano_pnv')->nullable();
            $table->string('tipo_pista', 50)->nullable();
            $table->string('sentido', 50)->nullable();

            // Status
            $table->enum('situacao', ['Ativo', 'Inativo'])->default('Ativo');
            $table->date('data_inativacao')->nullable();

            // Coordenadas (CRÍTICO para mapas)
            $table->decimal('latitude', 10, 7);   // -99.9999999
            $table->decimal('longitude', 10, 7);  // -999.9999999

            // Metadados
            $table->string('fonte', 50)->default('ANTT');  // Origem do dado
            $table->date('data_importacao')->nullable();

            // Índices para performance
            $table->index('situacao');
            $table->index('rodovia');
            $table->index('uf');
            $table->index(['latitude', 'longitude']);  // Busca geográfica

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pracas_pedagio');
    }
};
