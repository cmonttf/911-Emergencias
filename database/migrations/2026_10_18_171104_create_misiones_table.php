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
        Schema::create('misiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jugador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tipo_mision_id')->constrained('tipos_mision');
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->string('direccion');
            $table->string('estado');
            $table->timestamp('vence_en');
            $table->timestamps();

            $table->index(['jugador_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('misiones');
    }
};
