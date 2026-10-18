<?php

namespace App\Dominio\Misiones\Actions;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Clientes\OsrmClient;
use App\Dominio\Misiones\Models\Mision;

/**
 * Calcula, para una misión, las unidades disponibles del jugador ordenadas
 * por tiempo estimado de llegada (RF-24), consultando a OSRM la duración
 * real del viaje desde cada edificio hasta la misión.
 */
class CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction
{
    public function __construct(private readonly OsrmClient $osrm) {}

    /**
     * @return list<array{vehiculo: Vehiculo, duracionSegundos: int}> Ordenado de menor a mayor tiempo de llegada.
     */
    public function ejecutar(Mision $mision): array
    {
        $vehiculosDisponibles = Vehiculo::query()
            ->whereHas('edificio', fn ($consulta) => $consulta->where('jugador_id', $mision->jugador_id))
            ->where('estado', EstadoVehiculo::Disponible)
            ->with(['edificio', 'tipoVehiculo'])
            ->get();

        return $vehiculosDisponibles
            ->map(function (Vehiculo $vehiculo) use ($mision) {
                $resultadoRuta = $this->osrm->calcularRuta($vehiculo->edificio->puntoGeografico(), $mision->puntoGeografico());

                return [
                    'vehiculo' => $vehiculo,
                    'duracionSegundos' => $resultadoRuta->duracionSegundos,
                ];
            })
            ->sortBy('duracionSegundos')
            ->values()
            ->all();
    }
}
