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
        Schema::create('tipos_edificio', function (Blueprint $table) {
            $table->id();
            $table->string('institucion');
            $table->string('nombre');
            $table->unsignedInteger('costo');
            $table->unsignedInteger('estacionamientos_base');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_edificio');
    }
};
