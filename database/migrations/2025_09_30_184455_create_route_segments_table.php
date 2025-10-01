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
        Schema::create('route_segments', function (Blueprint $table) {
            $table->id();
            $table->decimal('origin_lat', 10, 8)->comment('Latitude de origem');
            $table->decimal('origin_lng', 11, 8)->comment('Longitude de origem');
            $table->decimal('destination_lat', 10, 8)->comment('Latitude de destino');
            $table->decimal('destination_lng', 11, 8)->comment('Longitude de destino');
            $table->text('polyline')->comment('Polyline codificada do Google (encoded polyline)');
            $table->integer('distance_meters')->comment('Distância em metros');
            $table->integer('duration_seconds')->comment('Duração em segundos');
            $table->timestamp('expires_at')->nullable()->comment('Data de expiração do cache');
            $table->timestamps();

            // Índice composto para busca rápida por origem-destino
            $table->index(['origin_lat', 'origin_lng', 'destination_lat', 'destination_lng'], 'route_origin_dest_idx');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_segments');
    }
};
