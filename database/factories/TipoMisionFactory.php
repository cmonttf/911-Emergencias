<?php

namespace Database\Factories;

use App\Dominio\Infraestructura\Enums\Institucion;
use App\Dominio\Misiones\Models\TipoMision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoMision>
 */
class TipoMisionFactory extends Factory
{
    protected $model = TipoMision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Fuego en cocina',
            'institucion_principal' => Institucion::Bomberos,
            'descripcion' => 'Principio de incendio en la cocina de una vivienda.',
            'creditos_min' => 250,
            'creditos_max' => 300,
            'tiempo_limite_segundos' => 600,
        ];
    }
}
