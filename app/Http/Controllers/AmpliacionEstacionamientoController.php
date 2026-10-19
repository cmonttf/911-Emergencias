<?php

namespace App\Http\Controllers;

use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Infraestructura\Actions\AmpliarEstacionamientosEdificioAction;
use App\Dominio\Infraestructura\Exceptions\EstacionamientosMaximosAlcanzadosException;
use App\Dominio\Infraestructura\Models\Edificio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Amplía en 1 los estacionamientos de un edificio del jugador autenticado.
 */
class AmpliacionEstacionamientoController extends Controller
{
    public function __construct(private readonly AmpliarEstacionamientosEdificioAction $ampliarEstacionamientos) {}

    /**
     * @throws ValidationException cuando el edificio ya llegó al tope o no hay créditos suficientes.
     */
    public function store(Edificio $edificio, Request $request): RedirectResponse
    {
        Gate::allowIf($edificio->jugador_id === $request->user()->id);

        try {
            $this->ampliarEstacionamientos->ejecutar($request->user(), $edificio);
        } catch (EstacionamientosMaximosAlcanzadosException) {
            throw ValidationException::withMessages([
                'edificio_id' => 'Este edificio ya llegó al máximo de estacionamientos.',
            ]);
        } catch (SaldoInsuficienteException) {
            throw ValidationException::withMessages([
                'edificio_id' => 'No tienes créditos suficientes para ampliar los estacionamientos.',
            ]);
        }

        return redirect(route('dashboard', absolute: false));
    }
}
