<?php

namespace App\Dominio\Infraestructura\Models;

use App\Dominio\Infraestructura\Enums\Institucion;
use Database\Factories\TipoEdificioFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de edificio (cuartel de Bomberos, base SAMU, comisaría,
 * hospital). En la Fase 1 solo existe la fila de Cuartel de Bomberos.
 */
class TipoEdificio extends Model
{
    use HasFactory;

    protected $table = 'tipos_edificio';

    protected $fillable = [
        'institucion',
        'nombre',
        'costo',
        'estacionamientos_base',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'institucion' => Institucion::class,
        ];
    }

    public function edificios(): HasMany
    {
        return $this->hasMany(Edificio::class);
    }

    /**
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return TipoEdificioFactory::new();
    }
}
