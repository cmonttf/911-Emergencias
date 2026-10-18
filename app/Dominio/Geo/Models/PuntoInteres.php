<?php

namespace App\Dominio\Geo\Models;

use App\Dominio\Geo\Concerns\TienePuntoGeografico;
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
}
