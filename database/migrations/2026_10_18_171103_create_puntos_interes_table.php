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
        Schema::create('puntos_interes', function (Blueprint $table) {
            $table->id();
            $table->string('categoria');
            $table->string('nombre');
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->string('direccion');
            $table->string('comuna');
            $table->timestamps();

            $table->index(['latitud', 'longitud']);
            $table->index('comuna');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puntos_interes');
    }
};
