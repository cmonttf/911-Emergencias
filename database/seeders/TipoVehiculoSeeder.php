<?php

namespace Database\Seeders;

use App\Dominio\Flota\Enums\EspecialidadVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Infraestructura\Enums\Institucion;
use Illuminate\Database\Seeder;

/**
 * Catálogo de tipos de vehículo de Bomberos (sección "Catálogos
 * institucionales" del ERS): una especialidad por carro.
 */
class TipoVehiculoSeeder extends Seeder
{
    public function run(): void
    {
        $tiposPorEspecialidad = [
            EspecialidadVehiculo::Agua->value => ['nombre' => 'Carro Bomba', 'costo' => 5_000],
            EspecialidadVehiculo::Rescate->value => ['nombre' => 'Carro Rescate', 'costo' => 7_000],
            EspecialidadVehiculo::Escala->value => ['nombre' => 'Carro Escala', 'costo' => 9_000],
            EspecialidadVehiculo::MaterialesPeligrosos->value => ['nombre' => 'Carro de Materiales Peligrosos', 'costo' => 8_000],
            EspecialidadVehiculo::Mando->value => ['nombre' => 'Carro de Mando', 'costo' => 4_000],
        ];

        foreach ($tiposPorEspecialidad as $especialidad => $datos) {
            TipoVehiculo::query()->updateOrCreate(
                ['institucion' => Institucion::Bomberos, 'especialidad' => $especialidad],
                $datos,
            );
        }
    }
}
