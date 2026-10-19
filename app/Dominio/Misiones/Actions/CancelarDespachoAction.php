<?php

namespace App\Dominio\Misiones\Actions;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Exceptions\VehiculoNoDisponibleException;
use App\Dominio\Flota\Jobs\ProcesarRegresoVehiculoJob;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Clientes\OsrmClient;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Misiones\Events\DespachoCancelado;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Support\FactorVelocidadJuego;
use Illuminate\Support\Facades\DB;

/**
 * Cancela el despacho de una unidad en camino (RF-26): la unidad pasa de
 * "en ruta" directo a "regresando", calculando la ruta de vuelta desde el
 * punto donde va en ese momento (no desde el destino de la misión).
 */
class CancelarDespachoAction
{
    public function __construct(private readonly OsrmClient $osrm) {}

    /**
     * @throws VehiculoNoDisponibleException cuando el vehículo ya no está en camino.
     * @throws ServicioGeoNoDisponibleException cuando OSRM no calcula la ruta de vuelta.
     */
    public function ejecutar(Despacho $despacho): void
    {
        $despacho = $despacho->fresh();
        $vehiculo = $despacho->vehiculo;

        if ($vehiculo->estado !== EstadoVehiculo::EnRuta || $despacho->cancelado_en !== null) {
            throw new VehiculoNoDisponibleException(
                "El vehículo {$vehiculo->id} ya no está en camino, no se puede cancelar su despacho."
            );
        }

        // La llamada a OSRM se hace antes de tomar cualquier bloqueo de fila,
        // para no mantener un lock mientras se espera una respuesta de red.
        $resultadoRuta = $this->osrm->calcularRuta($vehiculo->posicionActual(), $vehiculo->edificio->puntoGeografico());

        DB::transaction(function () use ($despacho, $resultadoRuta) {
            $vehiculo = Vehiculo::query()->whereKey($despacho->vehiculo_id)->lockForUpdate()->first();
            $despachoActual = Despacho::query()->whereKey($despacho->id)->lockForUpdate()->first();

            if (! $vehiculo || $vehiculo->estado !== EstadoVehiculo::EnRuta || $despachoActual->cancelado_en !== null) {
                // El vehículo ya llegó o el despacho ya se canceló entre el
                // chequeo inicial y esta transacción: no hay nada que hacer.
                return;
            }

            $rutaRegreso = Ruta::query()->create([
                'geometria' => $resultadoRuta->geometria,
                'distancia_m' => $resultadoRuta->distanciaMetros,
                'duracion_s' => $resultadoRuta->duracionSegundos,
            ]);

            $salidaEn = now();
            $regresaEn = $salidaEn->clone()->addSeconds(
                FactorVelocidadJuego::escalarSegundos($resultadoRuta->duracionSegundos)
            );

            $despachoActual->update(['cancelado_en' => $salidaEn]);

            $vehiculo->update([
                'estado' => EstadoVehiculo::Regresando,
                'ruta_id' => $rutaRegreso->id,
                'sale_en' => $salidaEn,
                'regresa_en' => $regresaEn,
            ]);

            DespachoCancelado::dispatch($despachoActual);

            ProcesarRegresoVehiculoJob::dispatch($vehiculo->id)->delay($regresaEn);
        });
    }
}
