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
            $table->dropColumn('tiempo_resolucion_segundos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipos_mision', function (Blueprint $table) {
            $table->unsignedInteger('tiempo_resolucion_segundos')->after('tiempo_limite_segundos')->default(180);
        });
    }
};
