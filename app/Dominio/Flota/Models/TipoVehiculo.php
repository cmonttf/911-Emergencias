<?php

namespace App\Dominio\Flota\Models;

use App\Dominio\Flota\Enums\EspecialidadVehiculo;
use App\Dominio\Infraestructura\Enums\Institucion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de vehículo (carro bomba, carro rescate, etc.). En la
 * Fase 1 solo existen las especialidades de Bomberos.
 */
class TipoVehiculo extends Model
{
    use HasFactory;

    protected $table = 'tipos_vehiculo';

    protected $fillable = [
        'institucion',
        'nombre',
        'especialidad',
        'costo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'institucion' => Institucion::class,
            'especialidad' => EspecialidadVehiculo::class,
        ];
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }
}
