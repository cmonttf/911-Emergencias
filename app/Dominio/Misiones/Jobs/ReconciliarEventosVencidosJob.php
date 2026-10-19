<?php

namespace App\Dominio\Misiones\Jobs;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Jobs\ProcesarRegresoVehiculoJob;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Misiones\Actions\RegistrarLlegadaUnidadSolicitadaAction;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Corre cada 5 minutos desde el scheduler y corrige vehículos o misiones
 * cuyo evento ya debería haber ocurrido pero sigue sin procesarse (p. ej.
 * porque el job original falló y agotó sus reintentos). Cada job que vuelve
 * a despachar valida el estado esperado antes de actuar, así que
 * despacharlos de más aquí no duplica nada.
 */
class ReconciliarEventosVencidosJob implements ShouldQueue
{
    use Queueable;

    public function handle(RegistrarLlegadaUnidadSolicitadaAction $registrarLlegada): void
    {
        $this->despachosConLlegadaVencida()->each(
            fn (Despacho $despacho) => ProcesarLlegadaVehiculoJob::dispatch($despacho->id)
        );

        $this->vehiculosConRegresoVencido()->each(
            fn (Vehiculo $vehiculo) => ProcesarRegresoVehiculoJob::dispatch($vehiculo->id)
        );

        $this->misionesConVencimientoVencido()->each(
            fn (Mision $mision) => VencerMisionJob::dispatch($mision->id)
        );

        $this->misionesConResolucionVencida()->each(
            fn (Mision $mision) => ResolverMisionJob::dispatch($mision->id)
        );

        // Una unidad ya puede estar "en el lugar" sin que su misión se haya
        // actualizado (p. ej. el worker se cayó justo entre ambos pasos):
        // sin esto, esa misión quedaría atascada para siempre, cubierta
        // pero sin resuelve_en ni ResolverMisionJob agendado.
        $this->misionesConUnidadSolicitadaSinProcesar($registrarLlegada);
    }

    private function misionesConUnidadSolicitadaSinProcesar(RegistrarLlegadaUnidadSolicitadaAction $registrarLlegada): void
    {
        Mision::query()
            ->where('estado', EstadoMision::Generada)
            ->whereNull('resuelve_en')
            ->get()
            ->filter(fn (Mision $mision) => $mision->tieneAlgunaUnidadSolicitadaPresente())
            ->each(fn (Mision $mision) => $registrarLlegada->ejecutar($mision, cubiertaAntes: false));
    }

    /**
     * @return Collection<int, Despacho>
     */
    private function despachosConLlegadaVencida()
    {
        return Despacho::query()
            ->whereNull('cancelado_en')
            ->where('llegada_en', '<=', now())
            ->whereHas('vehiculo', fn ($consulta) => $consulta->where('estado', EstadoVehiculo::EnRuta))
            ->get();
    }

    /**
     * @return Collection<int, Vehiculo>
     */
    private function vehiculosConRegresoVencido()
    {
        return Vehiculo::query()
            ->where('estado', EstadoVehiculo::Regresando)
            ->whereNotNull('regresa_en')
            ->where('regresa_en', '<=', now())
            ->get();
    }

    /**
     * @return Collection<int, Mision>
     */
    private function misionesConVencimientoVencido()
    {
        return Mision::query()
            ->whereIn('estado', [EstadoMision::Generada, EstadoMision::EnAtencion])
            ->where('vence_en', '<=', now())
            ->get();
    }

    /**
     * @return Collection<int, Mision>
     */
    private function misionesConResolucionVencida()
    {
        return Mision::query()
            ->where('estado', EstadoMision::EnAtencion)
            ->whereNotNull('resuelve_en')
            ->where('resuelve_en', '<=', now())
            ->get();
    }
}
