<?php

namespace Database\Factories;

use App\Dominio\Infraestructura\Enums\Institucion;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoEdificio>
 */
class TipoEdificioFactory extends Factory
{
    protected $model = TipoEdificio::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institucion' => Institucion::Bomberos,
            'nombre' => 'Cuartel de Bomberos',
            'costo' => 2_000,
            'estacionamientos_base' => 2,
        ];
    }
}
