<?php

namespace App\Dominio\Misiones\Models;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Concerns\TienePuntoGeografico;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Support\FactorVelocidadJuego;
use App\Models\User;
use Database\Factories\MisionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Una emergencia generada para un jugador en una dirección real (RF-20). Su
 * `estado` sigue la máquina Generada → EnAtencion → Completada | Fallida.
 */
class Mision extends Model
{
    use HasFactory, TienePuntoGeografico;

    protected $table = 'misiones';

    protected $fillable = [
        'jugador_id',
        'tipo_mision_id',
        'latitud',
        'longitud',
        'direccion',
        'estado',
        'vence_en',
        'resuelve_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoMision::class,
            'vence_en' => 'datetime',
            'resuelve_en' => 'datetime',
        ];
    }

    public function jugador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jugador_id');
    }

    public function tipoMision(): BelongsTo
    {
        return $this->belongsTo(TipoMision::class);
    }

    public function despachos(): HasMany
    {
        return $this->hasMany(Despacho::class);
    }

    /**
     * Vehículos actualmente asignados a esta misión (en camino o ya en el
     * lugar), usados para calcular qué requisitos siguen faltando.
     */
    public function vehiculosAsignados(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'mision_id');
    }

    /**
     * Vehículos que ya llegaron al lugar de la misión (a diferencia de los
     * que siguen en camino), los únicos que cuentan para cubrir requisitos.
     */
    public function vehiculosPresentes(): Collection
    {
        return $this->vehiculosAsignados()->where('estado', EstadoVehiculo::EnElLugar)->get();
    }

    /**
     * Compara las unidades presentes contra los requisitos del tipo de
     * misión, agrupados por tipo de vehículo.
     *
     * @return bool true si cada requisito tiene suficientes unidades presentes.
     */
    public function requisitosCubiertos(): bool
    {
        $presentesPorTipo = $this->vehiculosPresentes()->countBy('tipo_vehiculo_id');

        return $this->tipoMision->requisitos->every(
            fn (RequisitoTipoMision $requisito) => ($presentesPorTipo[$requisito->tipo_vehiculo_id] ?? 0) >= $requisito->cantidad
        );
    }

    /**
     * Requisitos del tipo de misión que todavía no están cubiertos, con la
     * cantidad de unidades que faltan (RF-22, "requisitos faltantes").
     *
     * @return Collection<int, array{tipoVehiculo: TipoVehiculo, faltan: int}>
     */
    public function requisitosFaltantes(): Collection
    {
        $presentesPorTipo = $this->vehiculosPresentes()->countBy('tipo_vehiculo_id');

        return $this->tipoMision->requisitos
            ->map(fn (RequisitoTipoMision $requisito) => [
                'tipoVehiculo' => $requisito->tipoVehiculo,
                'faltan' => max(0, $requisito->cantidad - ($presentesPorTipo[$requisito->tipo_vehiculo_id] ?? 0)),
            ])
            ->filter(fn (array $item) => $item['faltan'] > 0)
            ->values();
    }

    /**
     * Recompensa en créditos por completar esta misión (RF-23): entre el
     * mínimo y el máximo propios de su tipo, más cerca del máximo mientras
     * menos de su presupuesto de tiempo (desde que se generó hasta que
     * vence) se haya usado al completarla. Nunca sale del rango global
     * configurado, sin importar lo que diga el tipo de misión.
     */
    public function calcularRecompensaCreditos(): int
    {
        $tipoMision = $this->tipoMision;

        $presupuestoSegundos = $this->created_at->diffInSeconds($this->vence_en);
        $usadoSegundos = $this->created_at->diffInSeconds(now());

        $fraccionUsada = $presupuestoSegundos > 0
            ? min(1.0, max(0.0, $usadoSegundos / $presupuestoSegundos))
            : 1.0;

        $factorRapidez = 1 - $fraccionUsada;

        $recompensa = $tipoMision->creditos_min
            + ($tipoMision->creditos_max - $tipoMision->creditos_min) * $factorRapidez;

        return (int) round(min(
            config('juego.recompensa_mision_maxima'),
            max(config('juego.recompensa_mision_minima'), $recompensa),
        ));
    }

    /**
     * Cuántas unidades presentes son de un tipo de vehículo que la misión
     * pide, sin importar si alcanzan para cubrir sus requisitos. Las que no
     * son de un tipo solicitado no cuentan para nada de lo siguiente.
     */
    private function totalPresentesSolicitados(): int
    {
        $tipoVehiculoIdsSolicitados = $this->tipoMision->requisitos->pluck('tipo_vehiculo_id');

        return $this->vehiculosPresentes()
            ->whereIn('tipo_vehiculo_id', $tipoVehiculoIdsSolicitados)
            ->count();
    }

    /**
     * Si ya llegó al menos una unidad de un tipo que la misión pide, cubra
     * o no todos sus requisitos todavía (RF-24): el cronómetro de
     * resolución arranca con la primera, no hay que esperar a que estén
     * todas.
     */
    public function tieneAlgunaUnidadSolicitadaPresente(): bool
    {
        return $this->totalPresentesSolicitados() > 0;
    }

    /**
     * Tiempo de resolución (RF-24) para el estado actual de la misión: un
     * cronómetro fijo de 3 minutos (`juego.tiempo_resolucion_base_segundos`)
     * igual para toda misión. Mientras siga faltando alguna unidad
     * solicitada, se suma un extra por cada una que falte. Ya cubierta del
     * todo, cada unidad de más descuenta un tramo fijo al tiempo. Un piso
     * configurado evita que se resuelva al instante en cualquiera de los
     * dos casos.
     */
    public function calcularTiempoResolucionSegundos(): int
    {
        $totalRequerido = $this->tipoMision->requisitos->sum('cantidad');
        $totalPresentes = $this->totalPresentesSolicitados();

        $faltantes = max(0, $totalRequerido - $totalPresentes);
        $extras = max(0, $totalPresentes - $totalRequerido);

        $segundos = $faltantes > 0
            ? config('juego.tiempo_resolucion_base_segundos') + ($faltantes * config('juego.tiempo_extra_por_unidad_faltante_segundos'))
            : config('juego.tiempo_resolucion_base_segundos') - ($extras * config('juego.descuento_tiempo_resolucion_por_unidad_segundos'));

        return max(config('juego.tiempo_resolucion_minimo_segundos'), $segundos);
    }

    /**
     * Nuevo `resuelve_en` tras la llegada de una unidad solicitada (RF-24).
     * Si esta llegada recién termina de cubrir los requisitos (antes
     * faltaba alguna unidad, ahora no falta ninguna), recorta el tiempo que
     * quedaba en `juego.descuento_al_completar_cobertura` en vez de
     * recalcularlo desde cero. En cualquier otro caso —primera llegada,
     * sigue faltando alguna, o ya estaba cubierta y esta es una unidad de
     * más— usa calcularTiempoResolucionSegundos().
     */
    public function calcularResuelveEnTrasLlegada(bool $cubiertaAntes): Carbon
    {
        if (! $cubiertaAntes && $this->requisitosCubiertos() && $this->resuelve_en) {
            $segundosRestantes = max(0, $this->resuelve_en->getTimestamp() - now()->getTimestamp());
            $segundosRecortados = (int) round($segundosRestantes * (1 - config('juego.descuento_al_completar_cobertura')));

            return now()->addSeconds(max(config('juego.tiempo_resolucion_minimo_segundos'), $segundosRecortados));
        }

        return now()->addSeconds(FactorVelocidadJuego::escalarSegundos($this->calcularTiempoResolucionSegundos()));
    }

    /**
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return MisionFactory::new();
    }
}
