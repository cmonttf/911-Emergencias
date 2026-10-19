<?php

namespace App\Dominio\Misiones\Jobs;

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Dominio\Flota\Actions\RegresarVehiculoABaseAction;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Events\MisionCompletada;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve una misión "en atención" cuyo tiempo de resolución se cumplió:
 * abona sus créditos y devuelve a la base las unidades presentes. Vuelve a
 * comprobar los requisitos antes de completar, por si una unidad presente
 * se canceló mientras tanto (ya no correspondería completarla).
 */
class ResolverMisionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $misionId) {}

    public function handle(RegistrarTransaccionAction $registrarTransaccion, RegresarVehiculoABaseAction $regresarVehiculo): void
    {
        $mision = DB::transaction(function () use ($registrarTransaccion) {
            $mision = Mision::query()->whereKey($this->misionId)->lockForUpdate()->first();

            if (! $mision || $mision->estado !== EstadoMision::EnAtencion || ! $mision->requisitosCubiertos()) {
                return null;
            }

            $mision->update(['estado' => EstadoMision::Completada, 'resuelve_en' => null]);

            $registrarTransaccion->ejecutar(
                jugador: $mision->jugador,
                moneda: Moneda::Creditos,
                monto: $mision->calcularRecompensaCreditos(),
                motivo: MotivoTransaccion::RecompensaMision,
                referencia: $mision,
            );

            MisionCompletada::dispatch($mision);

            return $mision;
        });

        if (! $mision) {
            return;
        }

        // Las llamadas a OSRM quedan fuera de la transacción anterior para
        // no mantener bloqueos de fila mientras se espera una red externa.
        foreach ($mision->vehiculosPresentes() as $vehiculo) {
            $regresarVehiculo->ejecutar($vehiculo, $mision->puntoGeografico());
        }
    }
}
