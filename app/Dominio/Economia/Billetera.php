<?php

namespace App\Dominio\Economia;

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Models\Transaccion;
use App\Models\User;

/**
 * Calcula el saldo de un jugador a partir de su libro de transacciones. No
 * existe una columna de saldo editable en ninguna tabla: esta clase es la
 * única fuente de verdad sobre cuántos créditos o moneda premium tiene un
 * jugador en un momento dado.
 */
class Billetera
{
    public function __construct(private readonly User $jugador) {}

    /**
     * Suma todos los movimientos del jugador para la moneda indicada.
     *
     * @return int Saldo actual; nunca es negativo si todas las transacciones
     *             se registraron a través de {@see RegistrarTransaccionAction}.
     */
    public function saldo(Moneda $moneda): int
    {
        return (int) Transaccion::query()
            ->where('jugador_id', $this->jugador->id)
            ->where('moneda', $moneda)
            ->sum('monto');
    }
}
