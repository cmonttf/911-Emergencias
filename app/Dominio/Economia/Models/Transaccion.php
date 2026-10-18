<?php

namespace App\Dominio\Economia\Models;

use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un movimiento inmutable del libro de transacciones de un jugador. Solo se
 * inserta, nunca se actualiza ni se elimina: el saldo de un jugador se
 * calcula sumando sus transacciones, no se guarda en una columna aparte.
 */
class Transaccion extends Model
{
    protected $table = 'transacciones';

    public const string CREATED_AT = 'creada_en';

    public const ?string UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'jugador_id',
        'moneda',
        'monto',
        'motivo',
        'referencia_type',
        'referencia_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moneda' => Moneda::class,
            'motivo' => MotivoTransaccion::class,
        ];
    }

    public function jugador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jugador_id');
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }
}
