<?php

namespace Database\Factories;

use App\Dominio\Geo\Models\Ruta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ruta>
 */
class RutaFactory extends Factory
{
    protected $model = Ruta::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'geometria' => [
                [-73.0498, -36.8270],
                [-73.0450, -36.8250],
            ],
            'distancia_m' => 1200,
            'duracion_s' => 180,
        ];
    }
}
