<?php

namespace App\Dominio\Infraestructura\Models;

use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Concerns\TienePuntoGeografico;
use App\Models\User;
use Database\Factories\EdificioFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un edificio construido por un jugador en una ubicación real del mapa
 * (RF-50). En la Fase 1 solo existen cuarteles de Bomberos.
 */
class Edificio extends Model
{
    use HasFactory, TienePuntoGeografico;

    protected $table = 'edificios';

    protected $fillable = [
        'jugador_id',
        'tipo_edificio_id',
        'estacionamientos_extra',
        'nombre',
        'direccion',
        'latitud',
        'longitud',
    ];

    public function jugador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jugador_id');
    }

    public function tipoEdificio(): BelongsTo
    {
        return $this->belongsTo(TipoEdificio::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    /**
     * Cantidad de vehículos que ya ocupan un estacionamiento del edificio,
     * usada para no superar `estacionamientosTotales()` (RF-51).
     */
    public function vehiculosComprados(): int
    {
        return $this->vehiculos()->count();
    }

    /**
     * Estacionamientos del edificio: los base de su tipo, más los que este
     * edificio en particular haya ampliado (AmpliarEstacionamientosEdificioAction).
     */
    public function estacionamientosTotales(): int
    {
        return $this->tipoEdificio->estacionamientos_base + $this->estacionamientos_extra;
    }

    /**
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return EdificioFactory::new();
    }
}
