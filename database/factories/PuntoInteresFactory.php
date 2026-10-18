<?php

namespace Database\Factories;

use App\Dominio\Geo\Models\PuntoInteres;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PuntoInteres>
 */
class PuntoInteresFactory extends Factory
{
    protected $model = PuntoInteres::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria' => 'vivienda',
            'nombre' => fake()->streetAddress(),
            'latitud' => fake()->latitude(-36.85, -36.80),
            'longitud' => fake()->longitude(-73.07, -73.02),
            'direccion' => fake()->streetAddress().', Concepción',
            'comuna' => 'Concepción',
        ];
    }
}
