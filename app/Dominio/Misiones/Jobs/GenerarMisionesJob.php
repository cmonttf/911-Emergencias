<?php

namespace App\Dominio\Misiones\Jobs;

use App\Dominio\Geo\Models\PuntoInteres;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Events\MisionGenerada;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Models\TipoMision;
use App\Dominio\Misiones\Support\FactorVelocidadJuego;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Genera una misión nueva para cada jugador con cupo libre (RF-20, RF-21):
 * elige un tipo de misión, un edificio propio y un punto de interés real
 * dentro del radio configurado alrededor de ese edificio.
 *
 * Corre cada minuto desde el scheduler (routes/console.php). Si un jugador
 * no tiene puntos de interés importados cerca de sus edificios, simplemente
 * no se le genera ninguna misión en esa vuelta.
 */
class GenerarMisionesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $this->jugadoresConCupoLibre()->each($this->generarMisionParaJugador(...));
    }

    /**
     * Jugadores con al menos un edificio y menos misiones activas que su
     * tope (RF-21): la cantidad de edificios que tienen, más el extra
     * configurado en `juego.tope_misiones_extra_por_jugador`.
     *
     * @return Collection<int, User>
     */
    private function jugadoresConCupoLibre()
    {
        return User::query()
            ->withCount('edificios')
            ->withCount(['misiones as misiones_activas_count' => fn ($consulta) => $consulta->whereIn(
                'estado',
                [EstadoMision::Generada, EstadoMision::EnAtencion]
            )])
            ->having('edificios_count', '>', 0)
            ->havingRaw('misiones_activas_count < edificios_count + ?', [config('juego.tope_misiones_extra_por_jugador')])
            ->get();
    }

    private function generarMisionParaJugador(User $jugador): void
    {
        $tipoMision = TipoMision::query()->inRandomOrder()->first();
        $edificio = $jugador->edificios()->inRandomOrder()->first();

        if (! $tipoMision || ! $edificio) {
            return;
        }

        $puntoInteres = PuntoInteres::query()
            ->dentroDelRadio($edificio->puntoGeografico(), config('juego.radio_generacion_mision_metros'))
            ->inRandomOrder()
            ->first();

        if (! $puntoInteres) {
            return;
        }

        $venceEn = now()->addSeconds(FactorVelocidadJuego::escalarSegundos($tipoMision->tiempo_limite_segundos));

        $mision = Mision::query()->create([
            'jugador_id' => $jugador->id,
            'tipo_mision_id' => $tipoMision->id,
            'latitud' => $puntoInteres->latitud,
            'longitud' => $puntoInteres->longitud,
            'direccion' => $puntoInteres->direccion,
            'estado' => EstadoMision::Generada,
            'vence_en' => $venceEn,
        ]);

        MisionGenerada::dispatch($mision);

        VencerMisionJob::dispatch($mision->id)->delay($venceEn);
    }
}
