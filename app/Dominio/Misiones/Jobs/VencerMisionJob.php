<?php

namespace App\Dominio\Misiones\Jobs;

use App\Dominio\Misiones\Actions\RecallarUnidadesAsignadasAction;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Events\MisionFallida;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Vence una misión cuyo tiempo límite se cumplió sin cubrir requisitos
 * (RF-28): no abona créditos y recalla todas las unidades asignadas hacia
 * su base (RecallarUnidadesAsignadasAction), ya sea cancelando su despacho
 * (si iban en camino) o devolviéndolas desde el lugar (si ya habían
 * llegado).
 */
class VencerMisionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $misionId) {}

    public function handle(RecallarUnidadesAsignadasAction $recallarUnidades): void
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
        $recallarUnidades->ejecutar($mision);
    }
}
