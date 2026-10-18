<?php

namespace App\Dominio\Misiones\Models;

use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Models\Ruta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El envío de un vehículo a una misión: guarda la hora de salida y la hora
 * estimada de llegada que el cliente usa para interpolar la posición
 * (RNF-02). Un despacho cancelado registra `cancelado_en`.
 */
class Despacho extends Model
{
    use HasFactory;

    protected $table = 'despachos';

    protected $fillable = [
        'mision_id',
        'vehiculo_id',
        'ruta_id',
        'salida_en',
        'llegada_en',
        'cancelado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salida_en' => 'datetime',
            'llegada_en' => 'datetime',
            'cancelado_en' => 'datetime',
        ];
    }

    public function mision(): BelongsTo
    {
        return $this->belongsTo(Mision::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }
}
