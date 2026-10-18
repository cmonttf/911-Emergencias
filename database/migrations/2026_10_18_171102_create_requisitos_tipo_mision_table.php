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
        Schema::create('requisitos_tipo_mision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_mision_id')->constrained('tipos_mision')->cascadeOnDelete();
            $table->foreignId('tipo_vehiculo_id')->constrained('tipos_vehiculo');
            $table->unsignedInteger('cantidad');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisitos_tipo_mision');
    }
};
