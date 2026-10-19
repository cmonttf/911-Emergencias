<?php

namespace App\Dominio\Misiones\Jobs;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Events\VehiculoLlego;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Procesa la llegada de una unidad despachada a una misión: la pasa a "en el
 * lugar" y, si es de un tipo que la misión pide, pasa la misión a
 * "en atención" y agenda (o reprograma) su resolución. Valida el estado
 * esperado antes de actuar para ser idempotente.
 *
 * La misión entra en atención con la primera unidad solicitada que llega,
 * cubra o no todos sus requisitos todavía (RF-24): Mision::calcularResuelveEnTrasLlegada()
 * decide el tiempo según el caso (primera llegada parcial, llegada que
 * recién completa la cobertura, o unidad de más ya estando cubierta). Solo
 * se reprograma si el resultado adelanta lo ya agendado: mandar o recibir
 * más unidades nunca atrasa el término.
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

            $mision = Mision::query()->whereKey($despacho->mision_id)->lockForUpdate()->first();
            $cubiertaAntes = $mision?->requisitosCubiertos() ?? false;

            $vehiculo->update(['estado' => EstadoVehiculo::EnElLugar]);
            VehiculoLlego::dispatch($vehiculo);

            $yaEnAtencion = $mision?->estado === EstadoMision::EnAtencion;

            if (! $mision || (! $yaEnAtencion && $mision->estado !== EstadoMision::Generada)) {
                return;
            }

            if (! $mision->tieneAlgunaUnidadSolicitadaPresente()) {
                // Esta unidad no es de un tipo que la misión pida.
                return;
            }

            $resuelveEn = $mision->calcularResuelveEnTrasLlegada($cubiertaAntes);

            if ($yaEnAtencion && $mision->resuelve_en && $resuelveEn->greaterThanOrEqualTo($mision->resuelve_en)) {
                return;
            }

            $mision->update(['estado' => EstadoMision::EnAtencion, 'resuelve_en' => $resuelveEn]);

            ResolverMisionJob::dispatch($mision->id)->delay($resuelveEn);
        });
    }
}
