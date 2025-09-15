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
        Schema::create('route_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique(); // Hash dos waypoints para identificar a rota
            $table->json('waypoints'); // Coordenadas originais dos pontos
            $table->longText('route_coordinates'); // Coordenadas da rota calculada (JSON)
            $table->decimal('total_distance', 10, 3)->nullable(); // Distância total em km
            $table->integer('waypoints_count'); // Número de waypoints
            $table->string('source')->default('google_maps'); // Fonte da rota (google_maps, osrm, etc)
            $table->timestamp('expires_at')->nullable(); // Opcional: expiração do cache
            $table->timestamps();
            
            // Índices para performance
            $table->index('cache_key');
            $table->index('waypoints_count');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_cache');
    }
};
