<?php

namespace Database\Factories;

use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Edificio>
 */
class EdificioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jugador_id' => User::factory(),
            'tipo_edificio_id' => TipoEdificio::factory(),
            'nombre' => 'Cuartel '.fake()->streetName(),
            'direccion' => fake()->streetAddress().', Concepción',
            'latitud' => fake()->latitude(-36.85, -36.80),
            'longitud' => fake()->longitude(-73.07, -73.02),
        ];
    }
}
