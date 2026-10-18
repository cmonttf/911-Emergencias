<?php

namespace App\Dominio\Misiones\Jobs;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Events\VehiculoLlego;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Support\FactorVelocidadJuego;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Procesa la llegada de una unidad despachada a una misión: la pasa a "en el
 * lugar" y, si con ella ya se cubren los requisitos, pasa la misión a
 * "en atención" y agenda su resolución. Valida el estado esperado antes de
 * actuar para ser idempotente.
 */
class ProcesarLlegadaVehiculoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $despachoId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $despacho = Despacho::query()->whereKey($this->despachoId)->lockForUpdate()->first();

            if (! $despacho || $despacho->cancelado_en !== null) {
                return;
            }

            $vehiculo = Vehiculo::query()->whereKey($despacho->vehiculo_id)->lockForUpdate()->first();

            if (! $vehiculo || $vehiculo->estado !== EstadoVehiculo::EnRuta) {
                return;
            }

            $vehiculo->update(['estado' => EstadoVehiculo::EnElLugar]);
            VehiculoLlego::dispatch($vehiculo);

            $mision = Mision::query()->whereKey($despacho->mision_id)->lockForUpdate()->first();

            if ($mision && $mision->estado === EstadoMision::Generada && $mision->requisitosCubiertos()) {
                $resuelveEn = now()->addSeconds(
                    FactorVelocidadJuego::escalarSegundos($mision->tipoMision->tiempo_resolucion_segundos)
                );

                $mision->update(['estado' => EstadoMision::EnAtencion, 'resuelve_en' => $resuelveEn]);

                ResolverMisionJob::dispatch($mision->id)->delay($resuelveEn);
            }
        });
    }
}
