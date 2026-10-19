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
            $table->dropColumn('creditos');
            $table->unsignedInteger('creditos_min')->after('descripcion');
            $table->unsignedInteger('creditos_max')->after('creditos_min');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipos_mision', function (Blueprint $table) {
            $table->dropColumn(['creditos_min', 'creditos_max']);
            $table->unsignedInteger('creditos')->after('descripcion');
        });
    }
};
