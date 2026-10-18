<?php

namespace App\Http\Controllers;

use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use App\Dominio\Infraestructura\Actions\ConstruirEdificioAction;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Http\Requests\StoreEdificioRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class EdificioController extends Controller
{
    public function __construct(private readonly ConstruirEdificioAction $construirEdificio) {}

    /**
     * Construye un edificio para el jugador autenticado en el punto del
     * mapa indicado (RF-50).
     *
     * @throws ValidationException cuando el jugador no tiene créditos suficientes.
     */
    public function store(StoreEdificioRequest $request): RedirectResponse
    {
        $tipoEdificio = TipoEdificio::query()->findOrFail($request->integer('tipo_edificio_id'));

        try {
            $this->construirEdificio->ejecutar(
                jugador: $request->user(),
                tipoEdificio: $tipoEdificio,
                punto: new PuntoGeografico($request->float('latitud'), $request->float('longitud')),
            );
        } catch (SaldoInsuficienteException) {
            throw ValidationException::withMessages([
                'tipo_edificio_id' => 'No tienes créditos suficientes para construir este edificio.',
            ]);
        }

        return redirect(route('dashboard', absolute: false));
    }
}
