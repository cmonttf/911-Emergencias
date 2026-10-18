<?php

namespace App\Http\Controllers;

use App\Dominio\Misiones\Actions\CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Listado y detalle de las misiones del jugador autenticado (RF-22 a RF-24).
 */
class MisionController extends Controller
{
    /**
     * @param  string  $orden  "antiguedad" (por defecto) o "distancia" desde el primer edificio del jugador.
     */
    public function index(Request $request): Response
    {
        $jugador = $request->user();
        $orden = $request->string('orden', 'antiguedad')->toString();

        $misiones = $jugador->misiones()->with('tipoMision')->get();

        if ($orden === 'distancia' && ($edificioReferencia = $jugador->edificios()->first())) {
            $puntoReferencia = $edificioReferencia->puntoGeografico();
            $misiones = $misiones
                ->sortBy(fn (Mision $mision) => $puntoReferencia->distanciaMetrosHacia($mision->puntoGeografico()))
                ->values();
        } else {
            $misiones = $misiones->sortByDesc('created_at')->values();
        }

        return Inertia::render('Juego/Misiones/Index', [
            'misiones' => $misiones,
            'orden' => $orden,
        ]);
    }

    public function show(
        Mision $mision,
        Request $request,
        CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction $calcularUnidadesDisponibles,
    ): Response {
        Gate::allowIf($mision->jugador_id === $request->user()->id);

        $mision->load([
            'tipoMision.requisitos.tipoVehiculo',
            'vehiculosAsignados.tipoVehiculo',
            'despachos' => fn ($consulta) => $consulta->whereNull('cancelado_en'),
        ]);

        $unidadesDisponibles = collect($calcularUnidadesDisponibles->ejecutar($mision))
            ->map(fn (array $item) => [
                'vehiculo' => $item['vehiculo'],
                'duracionSegundos' => $item['duracionSegundos'],
            ])
            ->values();

        return Inertia::render('Juego/Misiones/Show', [
            'mision' => $mision,
            'requisitosFaltantes' => $mision->requisitosFaltantes(),
            'unidadesDisponibles' => $unidadesDisponibles,
        ]);
    }
}
