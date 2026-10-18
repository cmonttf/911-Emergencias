<?php

namespace App\Dominio\Economia\Actions;

use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Economia\Models\Transaccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Registra un movimiento inmutable de créditos o moneda premium para un
 * jugador y valida que el saldo resultante no quede negativo.
 */
class RegistrarTransaccionAction
{
    /**
     * Inserta la transacción dentro de un bloqueo de fila sobre el jugador,
     * de modo que dos movimientos concurrentes del mismo jugador se
     * serialicen y el saldo nunca quede negativo (RNF-05).
     *
     * @param  int  $monto  Positivo para abonos, negativo para cargos.
     * @param  Model|null  $referencia  Entidad de origen del movimiento (p. ej. el edificio comprado), si aplica.
     *
     * @throws SaldoInsuficienteException cuando el movimiento dejaría el saldo en negativo.
     */
    public function ejecutar(
        User $jugador,
        Moneda $moneda,
        int $monto,
        MotivoTransaccion $motivo,
        ?Model $referencia = null,
    ): Transaccion {
        return DB::transaction(function () use ($jugador, $moneda, $monto, $motivo, $referencia) {
            User::query()->whereKey($jugador->id)->lockForUpdate()->firstOrFail();

            $saldoActual = (new Billetera($jugador))->saldo($moneda);

            if ($saldoActual + $monto < 0) {
                throw new SaldoInsuficienteException($jugador->id, $moneda, $saldoActual, $monto);
            }

            return Transaccion::query()->create([
                'jugador_id' => $jugador->id,
                'moneda' => $moneda,
                'monto' => $monto,
                'motivo' => $motivo,
                'referencia_type' => $referencia?->getMorphClass(),
                'referencia_id' => $referencia?->getKey(),
            ]);
        });
    }
}
