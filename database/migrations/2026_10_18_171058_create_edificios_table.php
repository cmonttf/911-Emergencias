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
        Schema::create('edificios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jugador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tipo_edificio_id')->constrained('tipos_edificio');
            $table->string('nombre');
            $table->string('direccion');
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->timestamps();

            $table->index(['latitud', 'longitud']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edificios');
    }
};
