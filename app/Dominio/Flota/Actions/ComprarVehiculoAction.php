<?php

namespace App\Dominio\Flota\Actions;

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Exceptions\SinEstacionamientosDisponiblesException;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Compra un vehículo para un edificio del jugador (RF-51), respetando el
 * tope de estacionamientos del edificio y cobrando el costo del catálogo.
 */
class ComprarVehiculoAction
{
    public function __construct(private readonly RegistrarTransaccionAction $registrarTransaccion) {}

    /**
     * @throws SinEstacionamientosDisponiblesException cuando el edificio ya ocupa todos sus estacionamientos.
     * @throws SaldoInsuficienteException cuando el jugador no tiene créditos suficientes.
     */
    public function ejecutar(User $jugador, Edificio $edificio, TipoVehiculo $tipoVehiculo): Vehiculo
    {
        return DB::transaction(function () use ($jugador, $edificio, $tipoVehiculo) {
            // Bloquea la fila del edificio para serializar compras
            // concurrentes y no superar sus estacionamientos (RNF-05).
            $edificio = Edificio::query()->whereKey($edificio->id)->lockForUpdate()->firstOrFail();

            if ($edificio->vehiculosComprados() >= $edificio->tipoEdificio->estacionamientos_base) {
                throw new SinEstacionamientosDisponiblesException(
                    "El edificio {$edificio->nombre} no tiene estacionamientos disponibles."
                );
            }

            $vehiculo = Vehiculo::query()->create([
                'edificio_id' => $edificio->id,
                'tipo_vehiculo_id' => $tipoVehiculo->id,
                'estado' => EstadoVehiculo::Disponible,
            ]);

            $this->registrarTransaccion->ejecutar(
                jugador: $jugador,
                moneda: Moneda::Creditos,
                monto: -$tipoVehiculo->costo,
                motivo: MotivoTransaccion::CompraVehiculo,
                referencia: $vehiculo,
            );

            return $vehiculo;
        });
    }
}
