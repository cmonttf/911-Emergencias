<?php

namespace Database\Factories;

use App\Dominio\Flota\Enums\EspecialidadVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Infraestructura\Enums\Institucion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoVehiculo>
 */
class TipoVehiculoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institucion' => Institucion::Bomberos,
            'nombre' => 'Carro Bomba',
            'especialidad' => EspecialidadVehiculo::Agua,
            'costo' => 5_000,
        ];
    }
}
