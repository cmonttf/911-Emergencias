<?php

namespace App\Dominio\Misiones\Models;

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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoMision::class,
            'vence_en' => 'datetime',
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
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return MisionFactory::new();
    }
}
