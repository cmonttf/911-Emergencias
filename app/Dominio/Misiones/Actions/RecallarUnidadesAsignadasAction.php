<?php

namespace App\Dominio\Misiones\Actions;

use App\Dominio\Flota\Actions\RegresarVehiculoABaseAction;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;

/**
 * Recalla hacia su base todas las unidades asignadas a una misión que ya
 * terminó (completada o fallida), sin importar si son más de las que
 * exigían sus requisitos: una unidad todavía en camino cancela su despacho
 * (recalculando la vuelta desde donde va, RF-26); una que ya llegó al
 * lugar vuelve desde ahí. Se usa tanto al resolver como al vencer una
 * misión (ResolverMisionJob, VencerMisionJob), fuera de cualquier
 * transacción con bloqueo de fila: recallar implica llamadas a OSRM, que
 * no deben hacerse mientras se sostiene un lock.
 */
class RecallarUnidadesAsignadasAction
{
    public function __construct(
        private readonly CancelarDespachoAction $cancelarDespacho,
        private readonly RegresarVehiculoABaseAction $regresarVehiculo,
    ) {}

    public function ejecutar(Mision $mision): void
    {
        foreach ($mision->vehiculosAsignados as $vehiculo) {
            match ($vehiculo->estado) {
                EstadoVehiculo::EnRuta => $this->cancelarDespachoDelVehiculo($vehiculo->id, $mision),
                EstadoVehiculo::EnElLugar => $this->regresarVehiculo->ejecutar($vehiculo, $mision->puntoGeografico()),
                default => null,
            };
        }
    }

    private function cancelarDespachoDelVehiculo(int $vehiculoId, Mision $mision): void
    {
        $despacho = Despacho::query()
            ->where('vehiculo_id', $vehiculoId)
            ->where('mision_id', $mision->id)
            ->whereNull('cancelado_en')
            ->latest()
            ->first();

        if ($despacho) {
            $this->cancelarDespacho->ejecutar($despacho);
        }
    }
}
