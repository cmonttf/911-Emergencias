<?php

namespace Database\Factories;

use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Despacho>
 */
class DespachoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mision_id' => Mision::factory(),
            'vehiculo_id' => Vehiculo::factory(),
            'ruta_id' => Ruta::factory(),
            'salida_en' => now(),
            'llegada_en' => now()->addMinutes(3),
        ];
    }
}
