<?php

namespace App\Dominio\Misiones\Actions;

use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Jobs\ResolverMisionJob;
use App\Dominio\Misiones\Models\Mision;

/**
 * Aplica el efecto de que una unidad de un tipo solicitado está presente en
 * una misión (RF-24): la pasa a "en atención" (si no lo estaba) y agenda o
 * reprograma su resolución. La usan tanto ProcesarLlegadaVehiculoJob (al
 * procesar la llegada de una unidad concreta) como ReconciliarEventosVencidosJob
 * (para corregir una misión cuya unidad ya está en el lugar pero que, por lo
 * que sea, nunca se llegó a procesar).
 */
class RegistrarLlegadaUnidadSolicitadaAction
{
    public function ejecutar(Mision $mision, bool $cubiertaAntes): void
    {
        if (! $mision->tieneAlgunaUnidadSolicitadaPresente()) {
            return;
        }

        $yaEnAtencion = $mision->estado === EstadoMision::EnAtencion;

        if (! $yaEnAtencion && $mision->estado !== EstadoMision::Generada) {
            return;
        }

        $resuelveEn = $mision->calcularResuelveEnTrasLlegada($cubiertaAntes);

        if ($yaEnAtencion && $mision->resuelve_en && $resuelveEn->greaterThanOrEqualTo($mision->resuelve_en)) {
            return;
        }

        $mision->update(['estado' => EstadoMision::EnAtencion, 'resuelve_en' => $resuelveEn]);

        ResolverMisionJob::dispatch($mision->id)->delay($resuelveEn);
    }
}
