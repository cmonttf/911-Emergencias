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
        Schema::table('misiones', function (Blueprint $table) {
            // Hora en que debería resolverse una misión "en atención".
            // Sin esta columna, ReconciliarEventosVencidosJob no tendría
            // forma de detectar una resolución vencida sin procesar.
            $table->timestamp('resuelve_en')->nullable()->after('vence_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('misiones', function (Blueprint $table) {
            $table->dropColumn('resuelve_en');
        });
    }
};
