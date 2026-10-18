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
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edificio_id')->constrained('edificios')->cascadeOnDelete();
            $table->foreignId('tipo_vehiculo_id')->constrained('tipos_vehiculo');
            $table->string('estado');
            // mision_id y ruta_id se vinculan con una clave foránea en una
            // migración posterior, una vez existen las tablas `misiones` y
            // `rutas` (que a su vez referencian vehículos indirectamente a
            // través de los despachos).
            $table->unsignedBigInteger('mision_id')->nullable();
            $table->unsignedBigInteger('ruta_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
