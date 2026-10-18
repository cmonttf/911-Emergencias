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
        Schema::table('vehiculos', function (Blueprint $table) {
            // Hora estimada de llegada a la base cuando el vehículo está
            // "regresando" (tras completar, cancelar o fallar una misión).
            // Sin esta columna, ReconciliarEventosVencidosJob no tendría
            // forma de detectar un regreso vencido sin procesar.
            $table->timestamp('regresa_en')->nullable()->after('ruta_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn('regresa_en');
        });
    }
};
