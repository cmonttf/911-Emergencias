<?php

namespace App\Dominio\Misiones\Actions;

use App\Dominio\Flota\Actions\RegresarVehiculoABaseAction;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Support\Facades\Log;

/**
 * Recalla hacia su base todas las unidades asignadas a una misión que ya
 * terminó (completada o fallida), sin importar si son más de las que
 * exigían sus requisitos: una unidad todavía en camino cancela su despacho
 * (recalculando la vuelta desde donde va, RF-26); una que ya llegó al
 * lugar vuelve desde ahí. Se usa tanto al resolver como al vencer una
 * misión (ResolverMisionJob, VencerMisionJob), fuera de cualquier
 * transacción con bloqueo de fila: recallar implica llamadas a OSRM, que
 * no deben hacerse mientras se sostiene un lock.
 *
 * Cada unidad se recalla de forma aislada: si OSRM falla para una, no debe
 * impedir que se recallen las demás del mismo lote. La misión ya quedó en
 * un estado terminal (completada o fallida) antes de llegar aquí, así que
 * un reintento del job completo no volvería a repetir este paso; la unidad
 * que falló queda para que ReconciliarEventosVencidosJob la detecte y
 * reintente más adelante (ver misionesTerminadasConUnidadesSinRecallar()).
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
            try {
                match ($vehiculo->estado) {
                    EstadoVehiculo::EnRuta => $this->cancelarDespachoDelVehiculo($vehiculo->id, $mision),
                    EstadoVehiculo::EnElLugar => $this->regresarVehiculo->ejecutar($vehiculo, $mision->puntoGeografico()),
                    default => null,
                };
            } catch (ServicioGeoNoDisponibleException $excepcion) {
                Log::warning("No se pudo recallar el vehículo {$vehiculo->id} de la misión {$mision->id}: {$excepcion->getMessage()}");
            }
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
