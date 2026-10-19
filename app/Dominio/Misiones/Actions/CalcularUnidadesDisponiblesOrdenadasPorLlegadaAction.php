<?php

namespace App\Dominio\Misiones\Actions;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Clientes\OsrmClient;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Support\Facades\Log;

/**
 * Calcula, para una misión, las unidades disponibles del jugador ordenadas
 * por tiempo estimado de llegada (RF-24), consultando a OSRM la duración
 * real del viaje desde la posición actual de cada una hasta la misión.
 *
 * Una unidad "regresando" a su cuartel cuenta como disponible: todavía no
 * llegó, así que puede redirigirse a una misión nueva desde donde esté en
 * ese momento en vez de esperar a que llegue para recién despacharla de
 * nuevo (DespacharUnidadesAMisionAction).
 */
class CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction
{
    private const array ESTADOS_DESPACHABLES = [EstadoVehiculo::Disponible, EstadoVehiculo::Regresando];

    public function __construct(private readonly OsrmClient $osrm) {}

    /**
     * Si OSRM falla para un vehículo puntual, ese vehículo se excluye del
     * resultado en vez de romper la vista completa de la misión: no hay
     * razón para que una falla de red le oculte al jugador las unidades que
     * sí se pudieron calcular.
     *
     * @return list<array{vehiculo: Vehiculo, duracionSegundos: int}> Ordenado de menor a mayor tiempo de llegada.
     */
    public function ejecutar(Mision $mision): array
    {
        $vehiculosDisponibles = Vehiculo::query()
            ->whereHas('edificio', fn ($consulta) => $consulta->where('jugador_id', $mision->jugador_id))
            ->whereIn('estado', self::ESTADOS_DESPACHABLES)
            ->with(['edificio', 'tipoVehiculo', 'ruta'])
            ->get();

        return $vehiculosDisponibles
            ->map(function (Vehiculo $vehiculo) use ($mision) {
                try {
                    $resultadoRuta = $this->osrm->calcularRuta($vehiculo->posicionActual(), $mision->puntoGeografico());
                } catch (ServicioGeoNoDisponibleException $excepcion) {
                    Log::warning("No se pudo calcular la ruta del vehículo {$vehiculo->id} hacia la misión {$mision->id}: {$excepcion->getMessage()}");

                    return null;
                }

                return [
                    'vehiculo' => $vehiculo,
                    'duracionSegundos' => $resultadoRuta->duracionSegundos,
                ];
            })
            ->filter()
            ->sortBy('duracionSegundos')
            ->values()
            ->all();
    }
}
