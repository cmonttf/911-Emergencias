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
        Schema::create('rutas', function (Blueprint $table) {
            $table->id();
            // Lista de pares [longitud, latitud] tal como la entrega OSRM
            // (formato GeoJSON); ver decisión técnica en el plan de la Fase 1.
            $table->json('geometria');
            $table->unsignedInteger('distancia_m');
            $table->unsignedInteger('duracion_s');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rutas');
    }
};
