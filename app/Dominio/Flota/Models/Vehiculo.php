<?php

namespace App\Dominio\Flota\Models;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Geo\InterpoladorRuta;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use Carbon\Carbon;
use Database\Factories\VehiculoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un vehículo comprado por el jugador para un edificio. Su `estado` sigue la
 * máquina de 5 estados descrita en las reglas de diseño del proyecto.
 */
class Vehiculo extends Model
{
    use HasFactory;

    protected $table = 'vehiculos';

    protected $fillable = [
        'edificio_id',
        'tipo_vehiculo_id',
        'estado',
        'mision_id',
        'ruta_id',
        'sale_en',
        'regresa_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoVehiculo::class,
            'sale_en' => 'datetime',
            'regresa_en' => 'datetime',
        ];
    }

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class);
    }

    public function mision(): BelongsTo
    {
        return $this->belongsTo(Mision::class);
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }

    public function despachos(): HasMany
    {
        return $this->hasMany(Despacho::class);
    }

    /**
     * Datos que necesita el cliente para interpolar la posición del
     * vehículo en el mapa (RNF-02): hora de salida, hora de llegada y la
     * geometría de la ruta vigente. El tramo "en ruta" los toma del
     * despacho activo; el tramo "regresando" los toma de las columnas del
     * propio vehículo, porque ese regreso no siempre nace de un despacho
     * (también ocurre al completar o fallar una misión).
     *
     * @return array{salidaEn: string, llegadaEn: string, geometria: array}|null
     */
    public function tramoDeMovimiento(): ?array
    {
        if ($this->estado === EstadoVehiculo::EnRuta) {
            $despacho = $this->despachos()->whereNull('cancelado_en')->latest()->with('ruta')->first();

            if (! $despacho) {
                return null;
            }

            return [
                'salidaEn' => $despacho->salida_en->toIso8601String(),
                'llegadaEn' => $despacho->llegada_en->toIso8601String(),
                'geometria' => $despacho->ruta->geometria,
            ];
        }

        if ($this->estado === EstadoVehiculo::Regresando && $this->sale_en && $this->regresa_en) {
            return [
                'salidaEn' => $this->sale_en->toIso8601String(),
                'llegadaEn' => $this->regresa_en->toIso8601String(),
                'geometria' => $this->ruta?->geometria ?? [],
            ];
        }

        return null;
    }

    /**
     * Fracción recorrida del tramo de movimiento actual (RNF-02):
     * `f = (ahora - salida) / (llegada - salida)`. 0 si no se está
     * moviendo (nunca se usa en ese caso, ver `posicionActual()`).
     */
    public function fraccionRecorrida(): float
    {
        $tramo = $this->tramoDeMovimiento();

        if (! $tramo) {
            return 0.0;
        }

        $salida = Carbon::parse($tramo['salidaEn']);
        $duracionTotal = $salida->diffInSeconds(Carbon::parse($tramo['llegadaEn']));

        if ($duracionTotal <= 0) {
            return 1.0;
        }

        return min(1.0, max(0.0, $salida->diffInSeconds(now()) / $duracionTotal));
    }

    /**
     * Dónde está el vehículo ahora mismo: interpolado sobre su tramo de
     * movimiento si va en camino o regresando (la misma fórmula que usa el
     * cliente para dibujarlo, RNF-02), o su propio cuartel si está
     * disponible. Se usa para recalcular una ruta nueva desde ahí, nunca
     * para transmitir posiciones intermedias.
     */
    public function posicionActual(): PuntoGeografico
    {
        $tramo = $this->tramoDeMovimiento();

        if (! $tramo) {
            return $this->edificio->puntoGeografico();
        }

        return InterpoladorRuta::puntoEnFraccion($tramo['geometria'], $this->fraccionRecorrida());
    }

    /**
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return VehiculoFactory::new();
    }
}
