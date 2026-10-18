<?php

namespace App\Http\Controllers;

use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla principal del juego: el mapa con los edificios, misiones y
 * vehículos en movimiento del jugador, y los catálogos para construir y
 * comprar.
 */
class JuegoController extends Controller
{
    public function mapa(Request $request): Response
    {
        $jugador = $request->user();

        return Inertia::render('Juego/Mapa', [
            'edificios' => $jugador->edificios()->with('tipoEdificio', 'vehiculos.tipoVehiculo')->get(),
            'misiones' => $jugador->misiones()
                ->whereIn('estado', [EstadoMision::Generada, EstadoMision::EnAtencion])
                ->with('tipoMision')
                ->get(),
            'vehiculosEnMovimiento' => $this->vehiculosEnMovimiento($jugador),
            'tiposEdificio' => TipoEdificio::query()->get(),
            'tiposVehiculo' => TipoVehiculo::query()->get(),
            'saldoCreditos' => (new Billetera($jugador))->saldo(Moneda::Creditos),
        ]);
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
}
