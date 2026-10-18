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
            $table->foreign('mision_id')->references('id')->on('misiones')->nullOnDelete();
            $table->foreign('ruta_id')->references('id')->on('rutas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropForeign(['mision_id']);
            $table->dropForeign(['ruta_id']);
        });
    }
};
