<?php

namespace App\Dominio\Flota\Jobs;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Events\VehiculoDisponible;
use App\Dominio\Flota\Models\Vehiculo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Procesa la llegada de un vehículo "regresando" a su base. Valida el
 * estado esperado antes de actuar para ser idempotente: si el worker se
 * cae y el job se reintenta, o si `ReconciliarEventosVencidosJob` lo vuelve
 * a despachar, no pasa nada la segunda vez.
 */
class ProcesarRegresoVehiculoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $vehiculoId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $vehiculo = Vehiculo::query()->whereKey($this->vehiculoId)->lockForUpdate()->first();

            if (! $vehiculo || $vehiculo->estado !== EstadoVehiculo::Regresando) {
                return;
            }

            $vehiculo->update([
                'estado' => EstadoVehiculo::Disponible,
                'mision_id' => null,
                'ruta_id' => null,
                'regresa_en' => null,
            ]);

            VehiculoDisponible::dispatch($vehiculo);
        });
    }
}
