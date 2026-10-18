<?php

namespace App\Http\Controllers;

use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla principal del juego: el mapa con los edificios del jugador y los
 * catálogos necesarios para construir y comprar.
 */
class JuegoController extends Controller
{
    public function mapa(Request $request): Response
    {
        $jugador = $request->user();

        return Inertia::render('Juego/Mapa', [
            'edificios' => $jugador->edificios()->with('tipoEdificio', 'vehiculos.tipoVehiculo')->get(),
            'tiposEdificio' => TipoEdificio::query()->get(),
            'tiposVehiculo' => TipoVehiculo::query()->get(),
            'saldoCreditos' => (new Billetera($jugador))->saldo(Moneda::Creditos),
        ]);
    }
}
