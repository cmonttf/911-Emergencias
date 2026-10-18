<?php

namespace App\Dominio\Flota\Models;

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Models\Mision;
use Database\Factories\VehiculoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoVehiculo::class,
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

    /**
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return VehiculoFactory::new();
    }
}
