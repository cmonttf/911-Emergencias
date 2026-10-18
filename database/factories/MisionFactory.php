<?php

namespace Database\Factories;

use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Models\TipoMision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mision>
 */
class MisionFactory extends Factory
{
    protected $model = Mision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jugador_id' => User::factory(),
            'tipo_mision_id' => TipoMision::factory(),
            'latitud' => fake()->latitude(-36.85, -36.80),
            'longitud' => fake()->longitude(-73.07, -73.02),
            'direccion' => fake()->streetAddress().', Concepción',
            'estado' => EstadoMision::Generada,
            'vence_en' => now()->addMinutes(10),
        ];
    }
}
