<?php

namespace App\Http\Controllers;

use App\Dominio\Flota\Exceptions\VehiculoNoDisponibleException;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Misiones\Actions\CancelarDespachoAction;
use App\Dominio\Misiones\Actions\DespacharUnidadesAMisionAction;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use App\Http\Requests\StoreDespachoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Despacho manual de unidades a una misión y cancelación de un despacho en
 * camino (RF-25, RF-26).
 */
class DespachoController extends Controller
{
    public function __construct(
        private readonly DespacharUnidadesAMisionAction $despacharUnidades,
        private readonly CancelarDespachoAction $cancelarDespacho,
    ) {}

    /**
     * @throws ValidationException cuando el vehículo no está disponible o el servicio de rutas falla.
     */
    public function store(StoreDespachoRequest $request): RedirectResponse
    {
        $jugador = $request->user();
        $mision = Mision::query()->findOrFail($request->integer('mision_id'));
        $vehiculo = Vehiculo::query()->with('edificio')->findOrFail($request->integer('vehiculo_id'));

        Gate::allowIf($mision->jugador_id === $jugador->id);
        Gate::allowIf($vehiculo->edificio->jugador_id === $jugador->id);

        try {
            $this->despacharUnidades->ejecutar($vehiculo, $mision);
        } catch (VehiculoNoDisponibleException) {
            throw ValidationException::withMessages([
                'vehiculo_id' => 'Este vehículo ya no está disponible para despachar.',
            ]);
        } catch (ServicioGeoNoDisponibleException) {
            throw ValidationException::withMessages([
                'vehiculo_id' => 'No se pudo calcular la ruta hacia la misión. Intenta de nuevo.',
            ]);
        }

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * @throws ValidationException cuando el despacho ya no se puede cancelar.
     */
    public function destroy(Despacho $despacho, Request $request): RedirectResponse
    {
        Gate::allowIf($despacho->mision->jugador_id === $request->user()->id);

        try {
            $this->cancelarDespacho->ejecutar($despacho);
        } catch (VehiculoNoDisponibleException) {
            throw ValidationException::withMessages([
                'despacho' => 'Este despacho ya no se puede cancelar.',
            ]);
        } catch (ServicioGeoNoDisponibleException) {
            throw ValidationException::withMessages([
                'despacho' => 'No se pudo calcular la ruta de regreso. Intenta de nuevo.',
            ]);
        }

        return redirect(route('dashboard', absolute: false));
    }
}
