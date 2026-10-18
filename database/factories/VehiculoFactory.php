<?php

namespace Database\Factories;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehiculo>
 */
class VehiculoFactory extends Factory
{
    protected $model = Vehiculo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edificio_id' => Edificio::factory(),
            'tipo_vehiculo_id' => TipoVehiculo::factory(),
            'estado' => EstadoVehiculo::Disponible,
        ];
    }
}
