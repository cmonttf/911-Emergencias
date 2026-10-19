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
        Schema::table('tipos_mision', function (Blueprint $table) {
            // Null = cualquier punto de interés sirve (comportamiento
            // actual). Con un valor, GenerarMisionesJob solo elige puntos
            // de esa categoría (p. ej. "vegetacion" para incendios
            // forestales, en vez de poder caer en un supermercado).
            $table->string('categoria_punto_interes')->nullable()->after('descripcion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipos_mision', function (Blueprint $table) {
            $table->dropColumn('categoria_punto_interes');
        });
    }
};
