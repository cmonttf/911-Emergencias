<?php

namespace Database\Seeders;

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
        TipoEdificio::query()->updateOrCreate(
            ['institucion' => Institucion::Bomberos, 'nombre' => 'Cuartel de Bomberos'],
            ['costo' => 10_000, 'estacionamientos_base' => 2],
        );
    }
}
