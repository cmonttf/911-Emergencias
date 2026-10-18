<?php

namespace App\Dominio\Flota\Actions;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Jobs\ProcesarRegresoVehiculoJob;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Clientes\OsrmClient;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use App\Dominio\Misiones\Support\FactorVelocidadJuego;
use Illuminate\Support\Facades\DB;

/**
 * Envía un vehículo de vuelta a su base desde el punto indicado, usado tanto
 * al completar una misión (sin pacientes ni detenidos, se pasa directo de
 * "en el lugar" a "regresando") como al fallar una misión con unidades
 * presentes. El regreso de una unidad cancelada a medio camino lo maneja
 * `CancelarDespachoAction`, que interpola su posición actual.
 */
class RegresarVehiculoABaseAction
{
    public function __construct(private readonly OsrmClient $osrm) {}

    /**
     * @throws ServicioGeoNoDisponibleException cuando OSRM no calcula la ruta de vuelta.
     */
    public function ejecutar(Vehiculo $vehiculo, PuntoGeografico $origen): void
    {
        $resultadoRuta = $this->osrm->calcularRuta($origen, $vehiculo->edificio->puntoGeografico());

        DB::transaction(function () use ($vehiculo, $resultadoRuta) {
            $vehiculo = Vehiculo::query()->whereKey($vehiculo->id)->lockForUpdate()->first();

            if (! $vehiculo || in_array($vehiculo->estado, [EstadoVehiculo::Disponible, EstadoVehiculo::Regresando], true)) {
                // Ya se procesó (p. ej. otro job concurrente) o nunca llegó a
                // estar en la misión: no hay nada que regresar.
                return;
            }

            $ruta = Ruta::query()->create([
                'geometria' => $resultadoRuta->geometria,
                'distancia_m' => $resultadoRuta->distanciaMetros,
                'duracion_s' => $resultadoRuta->duracionSegundos,
            ]);

            $regresaEn = now()->addSeconds(FactorVelocidadJuego::escalarSegundos($resultadoRuta->duracionSegundos));

            $vehiculo->update([
                'estado' => EstadoVehiculo::Regresando,
                'ruta_id' => $ruta->id,
                'regresa_en' => $regresaEn,
            ]);

            ProcesarRegresoVehiculoJob::dispatch($vehiculo->id)->delay($regresaEn);
        });
    }
}
