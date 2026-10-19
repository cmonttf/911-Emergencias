<?php

namespace App\Dominio\Infraestructura\Actions;

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Infraestructura\Exceptions\EstacionamientosMaximosAlcanzadosException;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Amplía en 1 los estacionamientos de un edificio, cobrando el costo fijo
 * configurado, hasta el tope global de estacionamientos por edificio.
 */
class AmpliarEstacionamientosEdificioAction
{
    public function __construct(private readonly RegistrarTransaccionAction $registrarTransaccion) {}

    /**
     * @throws EstacionamientosMaximosAlcanzadosException cuando el edificio ya llegó al tope.
     * @throws SaldoInsuficienteException cuando el jugador no tiene créditos suficientes.
     */
    public function ejecutar(User $jugador, Edificio $edificio): Edificio
    {
        return DB::transaction(function () use ($jugador, $edificio) {
            // Bloquea la fila del edificio para serializar ampliaciones
            // concurrentes y no superar el tope (RNF-05).
            $edificio = Edificio::query()->whereKey($edificio->id)->lockForUpdate()->firstOrFail();

            if ($edificio->estacionamientosTotales() >= config('juego.estacionamientos_maximos')) {
                throw new EstacionamientosMaximosAlcanzadosException(
                    "El edificio {$edificio->nombre} ya llegó al máximo de estacionamientos."
                );
            }

            $edificio->increment('estacionamientos_extra');

            $this->registrarTransaccion->ejecutar(
                jugador: $jugador,
                moneda: Moneda::Creditos,
                monto: -config('juego.costo_ampliacion_estacionamiento'),
                motivo: MotivoTransaccion::AmpliacionEstacionamiento,
                referencia: $edificio,
            );

            return $edificio->fresh();
        });
    }
}
