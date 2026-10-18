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
        Schema::create('tipos_mision', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('institucion_principal');
            $table->string('descripcion');
            $table->unsignedInteger('creditos');
            $table->unsignedInteger('tiempo_limite_segundos');
            $table->unsignedInteger('tiempo_resolucion_segundos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_mision');
    }
};
