<?php

namespace App\Dominio\Misiones\Jobs;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Events\VehiculoLlego;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Misiones\Actions\RegistrarLlegadaUnidadSolicitadaAction;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Procesa la llegada de una unidad despachada a una misión: la pasa a "en el
 * lugar" y, si es de un tipo que la misión pide, pasa la misión a
 * "en atención" y agenda (o reprograma) su resolución mediante
 * RegistrarLlegadaUnidadSolicitadaAction. Valida el estado esperado antes de
 * actuar para ser idempotente.
 */
class ProcesarLlegadaVehiculoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $despachoId) {}

    public function handle(RegistrarLlegadaUnidadSolicitadaAction $registrarLlegada): void
    {
        DB::transaction(function () use ($registrarLlegada) {
            $despacho = Despacho::query()->whereKey($this->despachoId)->lockForUpdate()->first();

            if (! $despacho || $despacho->cancelado_en !== null) {
                return;
            }

            $vehiculo = Vehiculo::query()->whereKey($despacho->vehiculo_id)->lockForUpdate()->first();

            if (! $vehiculo || $vehiculo->estado !== EstadoVehiculo::EnRuta) {
                return;
            }

            $mision = Mision::query()->whereKey($despacho->mision_id)->lockForUpdate()->first();
            $cubiertaAntes = $mision?->requisitosCubiertos() ?? false;

            $vehiculo->update(['estado' => EstadoVehiculo::EnElLugar]);
            VehiculoLlego::dispatch($vehiculo);

            if ($mision) {
                $registrarLlegada->ejecutar($mision, $cubiertaAntes);
            }
        });
    }
}
