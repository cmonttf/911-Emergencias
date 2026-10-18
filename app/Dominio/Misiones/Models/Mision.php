<?php

namespace App\Dominio\Misiones\Models;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Concerns\TienePuntoGeografico;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Models\User;
use Database\Factories\MisionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return MisionFactory::new();
    }
}
