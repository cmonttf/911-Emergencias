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
        Schema::table('tipos_edificio', function (Blueprint $table) {
            // Null = el edificio no trae ningún vehículo al construirse
            // (comportamiento anterior). Con un valor, ConstruirEdificioAction
            // crea un vehículo de ese tipo gratis junto con el edificio.
            $table->foreignId('tipo_vehiculo_inicial_id')->nullable()->after('estacionamientos_base')
                ->constrained('tipos_vehiculo')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipos_edificio', function (Blueprint $table) {
            $table->dropForeign(['tipo_vehiculo_inicial_id']);
            $table->dropColumn('tipo_vehiculo_inicial_id');
        });
    }
};
