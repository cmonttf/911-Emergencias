<?php

namespace App\Dominio\Geo\Models;

use Database\Factories\RutaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una ruta calculada por OSRM entre dos puntos. `geometria` guarda la lista
 * de coordenadas `[longitud, latitud]` tal como la entrega OSRM (formato
 * GeoJSON), lista para que el cliente interpole la posición del vehículo
 * (RNF-02) sin que el servidor transmita posiciones intermedias.
 */
class Ruta extends Model
{
    use HasFactory;

    protected $table = 'rutas';

    protected $fillable = [
        'geometria',
        'distancia_m',
        'duracion_s',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'geometria' => 'array',
        ];
    }

    /**
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return RutaFactory::new();
    }
}
