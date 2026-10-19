<?php

namespace App\Dominio\Misiones\Models;

use App\Dominio\Flota\Enums\EspecialidadVehiculo;
use App\Dominio\Infraestructura\Enums\Institucion;
use Database\Factories\TipoMisionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de misión (incendio estructural, rescate vehicular,
 * etc.). En la Fase 1 solo existen tipos de misión de Bomberos.
 */
class TipoMision extends Model
{
    use HasFactory;

    protected $table = 'tipos_mision';

    protected $fillable = [
        'nombre',
        'institucion_principal',
        'descripcion',
        'categoria_punto_interes',
        'creditos_min',
        'creditos_max',
        'tiempo_limite_segundos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'institucion_principal' => Institucion::class,
        ];
    }

    public function requisitos(): HasMany
    {
        return $this->hasMany(RequisitoTipoMision::class);
    }

    public function misiones(): HasMany
    {
        return $this->hasMany(Mision::class);
    }

    /**
     * Tipos de misión que se cubren solo con el carro de agua básico, sin
     * exigir ningún vehículo de especialidad (rescate, escala, materiales
     * peligrosos o mando). Son los únicos disponibles para jugadores con
     * pocos edificios (`juego.edificios_minimos_mision_especial`).
     */
    public function scopeSinVehiculosEspeciales(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'requisitos.tipoVehiculo',
            fn ($consulta) => $consulta->where('especialidad', '!=', EspecialidadVehiculo::Agua->value)
        );
    }

    /**
     * Los modelos de dominio viven fuera de `App\Models`, así que la
     * resolución automática de factories de Laravel no los encuentra.
     */
    protected static function newFactory(): Factory
    {
        return TipoMisionFactory::new();
    }
}
