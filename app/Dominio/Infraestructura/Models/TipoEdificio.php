<?php

namespace App\Dominio\Infraestructura\Models;

use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Infraestructura\Enums\Institucion;
use Database\Factories\TipoEdificioFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'tipo_vehiculo_inicial_id',
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
     * Tipo de vehículo que trae gratis un edificio nuevo de este tipo (p. ej.
     * un carro de agua básico para el Cuartel de Bomberos). Null si este
     * tipo de edificio no trae ninguno al construirse.
     */
    public function tipoVehiculoInicial(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class, 'tipo_vehiculo_inicial_id');
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
