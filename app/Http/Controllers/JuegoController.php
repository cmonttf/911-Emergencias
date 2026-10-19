<?php

namespace App\Http\Controllers;

use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Dominio\Misiones\Actions\CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Models\Mision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla principal del juego: el mapa con los edificios, misiones y
 * vehículos en movimiento del jugador, y los catálogos para construir y
 * comprar.
 */
class JuegoController extends Controller
{
    public function mapa(Request $request, CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction $calcularUnidadesDisponibles): Response
    {
        $jugador = $request->user();

        return Inertia::render('Juego/Mapa', [
            'edificios' => $jugador->edificios()->with('tipoEdificio', 'vehiculos.tipoVehiculo')->get(),
            'misiones' => $this->misionesActivas($jugador, $calcularUnidadesDisponibles),
            'vehiculosEnMovimiento' => $this->vehiculosEnMovimiento($jugador),
            'unidadesEnElLugar' => $this->unidadesEnElLugar($jugador),
            'tiposEdificio' => TipoEdificio::query()->get(),
            'tiposVehiculo' => TipoVehiculo::query()->get(),
            'saldoCreditos' => (new Billetera($jugador))->saldo(Moneda::Creditos),
            'estacionamientosMaximos' => config('juego.estacionamientos_maximos'),
            'costoAmpliacionEstacionamiento' => config('juego.costo_ampliacion_estacionamiento'),
        ]);
    }

    /**
     * Misiones generadas o en atención del jugador, con las unidades
     * disponibles para despacharlas directamente desde el mapa (sin pasar
     * por su detalle).
     *
     * @return Collection<int, Mision>
     */
    private function misionesActivas(User $jugador, CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction $calcularUnidadesDisponibles)
    {
        return $jugador->misiones()
            ->whereIn('estado', [EstadoMision::Generada, EstadoMision::EnAtencion])
            ->with('tipoMision.requisitos.tipoVehiculo')
            ->get()
            ->each(function (Mision $mision) use ($calcularUnidadesDisponibles) {
                $mision->unidadesDisponibles = collect($calcularUnidadesDisponibles->ejecutar($mision))
                    ->map(fn (array $item) => [
                        'vehiculo' => $item['vehiculo'],
                        'duracionSegundos' => $item['duracionSegundos'],
                    ])
                    ->values();
            });
    }

    /**
     * Vehículos del jugador actualmente en camino o regresando, con los
     * datos que el cliente necesita para interpolar su posición (RNF-02).
     *
     * @return list<array{id: int, tipoVehiculo: string, estado: string, salidaEn: string, llegadaEn: string, geometria: array}>
     */
    private function vehiculosEnMovimiento(User $jugador): array
    {
        return Vehiculo::query()
            ->whereHas('edificio', fn ($consulta) => $consulta->where('jugador_id', $jugador->id))
            ->whereIn('estado', [EstadoVehiculo::EnRuta, EstadoVehiculo::Regresando])
            ->with('tipoVehiculo')
            ->get()
            ->map(function (Vehiculo $vehiculo) {
                $tramo = $vehiculo->tramoDeMovimiento();

                if (! $tramo) {
                    return null;
                }

                return [
                    'id' => $vehiculo->id,
                    'tipoVehiculo' => $vehiculo->tipoVehiculo->nombre,
                    'estado' => $vehiculo->estado->value,
                    ...$tramo,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Vehículos del jugador ya en el lugar de una misión, atendiéndola: no
     * se mueven (RNF-02 no transmite posiciones intermedias), así que basta
     * con la ubicación de la misión en vez de un tramo para interpolar.
     *
     * @return list<array{id: int, tipoVehiculo: string, latitud: float, longitud: float}>
     */
    private function unidadesEnElLugar(User $jugador): array
    {
        return Vehiculo::query()
            ->whereHas('edificio', fn ($consulta) => $consulta->where('jugador_id', $jugador->id))
            ->where('estado', EstadoVehiculo::EnElLugar)
            ->whereNotNull('mision_id')
            ->with(['tipoVehiculo', 'mision'])
            ->get()
            ->filter(fn (Vehiculo $vehiculo) => $vehiculo->mision !== null)
            ->map(fn (Vehiculo $vehiculo) => [
                'id' => $vehiculo->id,
                'tipoVehiculo' => $vehiculo->tipoVehiculo->nombre,
                'latitud' => $vehiculo->mision->latitud,
                'longitud' => $vehiculo->mision->longitud,
            ])
            ->values()
            ->all();
    }
}
