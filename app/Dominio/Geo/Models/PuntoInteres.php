<?php

namespace App\Dominio\Geo\Models;

use App\Dominio\Geo\Concerns\TienePuntoGeografico;
use Database\Factories\PuntoInteresFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una dirección real importada de OpenStreetMap (vía Nominatim) usada como
 * posible ubicación de una misión generada (RF-20).
 */
class PuntoInteres extends Model
{
    use HasFactory, TienePuntoGeografico;

    protected $table = 'puntos_interes';

    protected $fillable = [
        'categoria',
        'nombre',
        'latitud',
        'longitud',
        'direccion',
        'comuna',
    ];

    /**
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return PuntoInteresFactory::new();
    }
}
