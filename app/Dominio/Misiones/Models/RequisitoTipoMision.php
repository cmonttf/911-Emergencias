<?php

namespace App\Dominio\Misiones\Models;

use App\Dominio\Flota\Models\TipoVehiculo;
use Database\Factories\RequisitoTipoMisionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuántas unidades de un tipo de vehículo exige un tipo de misión para
 * considerarse atendida (RF-24, "requisitos faltantes").
 */
class RequisitoTipoMision extends Model
{
    use HasFactory;

    protected $table = 'requisitos_tipo_mision';

    protected $fillable = [
        'tipo_mision_id',
        'tipo_vehiculo_id',
        'cantidad',
    ];

    public function tipoMision(): BelongsTo
    {
        return $this->belongsTo(TipoMision::class);
    }

    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class);
    }

    /**
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return RequisitoTipoMisionFactory::new();
    }
}
