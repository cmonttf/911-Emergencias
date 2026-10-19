<?php

namespace App\Dominio\Misiones\Actions;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Exceptions\VehiculoNoDisponibleException;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Clientes\OsrmClient;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Misiones\Events\UnidadDespachada;
use App\Dominio\Misiones\Jobs\ProcesarLlegadaVehiculoJob;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Support\FactorVelocidadJuego;
use Illuminate\Support\Facades\DB;

/**
 * Despacha una unidad hacia una misión (RF-25): pide la ruta real a OSRM,
 * dentro de un bloqueo de fila crea el despacho y pasa el vehículo a
 * "en ruta", y agenda el job que procesará su llegada.
 *
 * Una unidad "regresando" a su cuartel también puede redirigirse a una
 * misión nueva: en ese caso la ruta se calcula desde su posición actual
 * interpolada sobre el regreso, no desde el cuartel (todavía no llegó).
 */
class DespacharUnidadesAMisionAction
{
    /** Estados desde los que una unidad puede despacharse a una misión. */
    private const array ESTADOS_DESPACHABLES = [EstadoVehiculo::Disponible, EstadoVehiculo::Regresando];

    public function __construct(private readonly OsrmClient $osrm) {}

    /**
     * @throws VehiculoNoDisponibleException cuando el vehículo no está disponible.
     * @throws ServicioGeoNoDisponibleException cuando OSRM no calcula la ruta.
     */
    public function ejecutar(Vehiculo $vehiculo, Mision $mision): Despacho
    {
        $resultadoRuta = $this->osrm->calcularRuta(
            $vehiculo->posicionActual(),
            $mision->puntoGeografico(),
        );

        return DB::transaction(function () use ($vehiculo, $mision, $resultadoRuta) {
            $vehiculo = Vehiculo::query()->whereKey($vehiculo->id)->lockForUpdate()->firstOrFail();

            if (! in_array($vehiculo->estado, self::ESTADOS_DESPACHABLES, true)) {
                throw new VehiculoNoDisponibleException(
                    "El vehículo {$vehiculo->id} no está disponible para despachar."
                );
            }

            $ruta = Ruta::query()->create([
                'geometria' => $resultadoRuta->geometria,
                'distancia_m' => $resultadoRuta->distanciaMetros,
                'duracion_s' => $resultadoRuta->duracionSegundos,
            ]);

            $salidaEn = now();
            $llegadaEn = $salidaEn->clone()->addSeconds(
                FactorVelocidadJuego::escalarSegundos($resultadoRuta->duracionSegundos)
            );

            $despacho = Despacho::query()->create([
                'mision_id' => $mision->id,
                'vehiculo_id' => $vehiculo->id,
                'ruta_id' => $ruta->id,
                'salida_en' => $salidaEn,
                'llegada_en' => $llegadaEn,
            ]);

            $vehiculo->update([
                'estado' => EstadoVehiculo::EnRuta,
                'mision_id' => $mision->id,
                'ruta_id' => $ruta->id,
                // Si venía "regresando", esas columnas ya no describen nada:
                // su tramo de movimiento ahora vive en el despacho recién
                // creado (ver Vehiculo::tramoDeMovimiento()).
                'sale_en' => null,
                'regresa_en' => null,
            ]);

            UnidadDespachada::dispatch($despacho);

            ProcesarLlegadaVehiculoJob::dispatch($despacho->id)->delay($llegadaEn);

            return $despacho;
        });
    }
}
