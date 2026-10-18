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
            // Hora de salida del tramo de regreso a la base. Junto con
            // regresa_en y ruta_id, le da al cliente los tres datos que
            // necesita para interpolar la posición también en el regreso
            // (RNF-02), no solo en el viaje de ida (que usa despachos.salida_en).
            $table->timestamp('sale_en')->nullable()->after('regresa_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn('sale_en');
        });
    }
};
