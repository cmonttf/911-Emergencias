<?php

namespace Database\Seeders;

use App\Dominio\Flota\Enums\EspecialidadVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Infraestructura\Enums\Institucion;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use Illuminate\Database\Seeder;

/**
 * Catálogo de tipos de edificio de la Fase 1: solo el Cuartel de Bomberos.
 * Base SAMU, comisaría y hospital se agregan en la Fase 2 con una fila más
 * en esta misma tabla, sin migraciones nuevas.
 */
class TipoEdificioSeeder extends Seeder
{
    public function run(): void
    {
        $carroDeAgua = TipoVehiculo::query()
            ->where('institucion', Institucion::Bomberos)
            ->where('especialidad', EspecialidadVehiculo::Agua)
            ->first();

        TipoEdificio::query()->updateOrCreate(
            ['institucion' => Institucion::Bomberos, 'nombre' => 'Cuartel de Bomberos'],
            [
                'costo' => 2_000,
                'estacionamientos_base' => 2,
                // El cuartel siempre trae su primer carro de agua gratis.
                'tipo_vehiculo_inicial_id' => $carroDeAgua?->id,
            ],
        );
    }
}
