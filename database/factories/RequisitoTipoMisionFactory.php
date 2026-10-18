<?php

namespace Database\Factories;

use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Misiones\Models\RequisitoTipoMision;
use App\Dominio\Misiones\Models\TipoMision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequisitoTipoMision>
 */
class RequisitoTipoMisionFactory extends Factory
{
    protected $model = RequisitoTipoMision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_mision_id' => TipoMision::factory(),
            'tipo_vehiculo_id' => TipoVehiculo::factory(),
            'cantidad' => 1,
        ];
    }
}
