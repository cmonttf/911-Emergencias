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
        Schema::create('despachos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mision_id')->constrained('misiones')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos');
            $table->foreignId('ruta_id')->nullable()->constrained('rutas');
            $table->timestamp('salida_en');
            $table->timestamp('llegada_en');
            $table->timestamp('cancelado_en')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('despachos');
    }
};
