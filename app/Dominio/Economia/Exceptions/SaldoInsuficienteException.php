<?php

namespace App\Dominio\Economia\Exceptions;

use App\Dominio\Economia\Enums\Moneda;
use RuntimeException;

/**
 * Se lanza cuando una transacción dejaría el saldo de un jugador en negativo.
 * El saldo nunca se persiste como columna editable: se recalcula sumando
 * `transacciones`, por lo que esta validación ocurre antes de insertar la
 * fila que causaría el descuadre.
 */
class SaldoInsuficienteException extends RuntimeException
{
    public function __construct(int $jugadorId, Moneda $moneda, int $saldoActual, int $montoSolicitado)
    {
        parent::__construct(
            "El jugador {$jugadorId} no tiene saldo suficiente de {$moneda->value} ".
            "(saldo actual: {$saldoActual}, monto solicitado: {$montoSolicitado})."
        );
    }
}
