<?php

namespace App\Dominio\Misiones\Jobs;

use App\Dominio\Flota\Actions\RegresarVehiculoABaseAction;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Misiones\Actions\CancelarDespachoAction;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Events\MisionFallida;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Vence una misión cuyo tiempo límite se cumplió sin cubrir requisitos
 * (RF-28): no abona créditos y recalla cualquier unidad asignada hacia su
 * base, ya sea cancelando su despacho (si iba en camino) o devolviéndola
 * desde el lugar (si ya había llegado).
 */
class VencerMisionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $misionId) {}

    public function handle(CancelarDespachoAction $cancelarDespacho, RegresarVehiculoABaseAction $regresarVehiculo): void
    {
        $mision = DB::transaction(function () {
            $mision = Mision::query()->whereKey($this->misionId)->lockForUpdate()->first();

            if (! $mision || ! in_array($mision->estado, [EstadoMision::Generada, EstadoMision::EnAtencion], true)) {
                return null;
            }

            if ($mision->requisitosCubiertos()) {
                // Ya está cubierta: le corresponde a ResolverMisionJob
                // completarla, no a este job vencerla.
                return null;
            }

            $mision->update(['estado' => EstadoMision::Fallida]);
            MisionFallida::dispatch($mision);

            return $mision;
        });

        if (! $mision) {
            return;
        }

        // Fuera de la transacción anterior: recallar unidades implica
        // llamadas a OSRM, que no deben hacerse mientras se sostiene un lock.
        foreach ($mision->vehiculosAsignados as $vehiculo) {
            match ($vehiculo->estado) {
                EstadoVehiculo::EnRuta => $this->cancelarDespachoDelVehiculo($vehiculo->id, $mision, $cancelarDespacho),
                EstadoVehiculo::EnElLugar => $regresarVehiculo->ejecutar($vehiculo, $mision->puntoGeografico()),
                default => null,
            };
        }
    }

    private function cancelarDespachoDelVehiculo(int $vehiculoId, Mision $mision, CancelarDespachoAction $cancelarDespacho): void
    {
        $despacho = Despacho::query()
            ->where('vehiculo_id', $vehiculoId)
            ->where('mision_id', $mision->id)
            ->whereNull('cancelado_en')
            ->latest()
            ->first();

        if ($despacho) {
            $cancelarDespacho->ejecutar($despacho);
        }
    }
}
