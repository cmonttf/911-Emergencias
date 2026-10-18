<?php

namespace App\Http\Controllers;

use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Flota\Actions\ComprarVehiculoAction;
use App\Dominio\Flota\Exceptions\SinEstacionamientosDisponiblesException;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Http\Requests\StoreVehiculoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VehiculoController extends Controller
{
    public function __construct(private readonly ComprarVehiculoAction $comprarVehiculo) {}

    /**
     * Compra un vehículo para un edificio del jugador autenticado (RF-51).
     *
     * @throws ValidationException cuando no hay estacionamientos o créditos suficientes.
     */
    public function store(StoreVehiculoRequest $request): RedirectResponse
    {
        $edificio = Edificio::query()->findOrFail($request->integer('edificio_id'));
        Gate::allowIf($edificio->jugador_id === $request->user()->id);

        $tipoVehiculo = TipoVehiculo::query()->findOrFail($request->integer('tipo_vehiculo_id'));

        try {
            $this->comprarVehiculo->ejecutar($request->user(), $edificio, $tipoVehiculo);
        } catch (SinEstacionamientosDisponiblesException) {
            throw ValidationException::withMessages([
                'edificio_id' => 'Este edificio no tiene estacionamientos disponibles.',
            ]);
        } catch (SaldoInsuficienteException) {
            throw ValidationException::withMessages([
                'tipo_vehiculo_id' => 'No tienes créditos suficientes para comprar este vehículo.',
            ]);
        }

        return redirect(route('dashboard', absolute: false));
    }
}
