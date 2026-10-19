<?php

namespace App\Dominio\Infraestructura\Actions;

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Clientes\NominatimClient;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Construye un edificio para un jugador en un punto del mapa (RF-50): resuelve
 * la dirección real del punto con Nominatim y cobra el costo del tipo de
 * edificio desde el libro de transacciones. Si el tipo de edificio trae un
 * vehículo inicial (p. ej. el carro de agua del Cuartel de Bomberos), lo
 * crea gratis junto con el edificio, sin ningún cargo adicional.
 */
class ConstruirEdificioAction
{
    public function __construct(
        private readonly NominatimClient $nominatim,
        private readonly RegistrarTransaccionAction $registrarTransaccion,
    ) {}

    /**
     * @throws SaldoInsuficienteException cuando el jugador no tiene créditos suficientes.
     * @throws ServicioGeoNoDisponibleException cuando Nominatim no resuelve el punto.
     */
    public function ejecutar(User $jugador, TipoEdificio $tipoEdificio, PuntoGeografico $punto): Edificio
    {
        $direccion = $this->nominatim->geocodificarInversa($punto);

        return DB::transaction(function () use ($jugador, $tipoEdificio, $punto, $direccion) {
            $edificio = Edificio::query()->create([
                'jugador_id' => $jugador->id,
                'tipo_edificio_id' => $tipoEdificio->id,
                'nombre' => $tipoEdificio->nombre,
                'direccion' => $direccion->direccionCompleta,
                'latitud' => $punto->latitud,
                'longitud' => $punto->longitud,
            ]);

            $this->registrarTransaccion->ejecutar(
                jugador: $jugador,
                moneda: Moneda::Creditos,
                monto: -$tipoEdificio->costo,
                motivo: MotivoTransaccion::ConstruccionEdificio,
                referencia: $edificio,
            );

            if ($tipoEdificio->tipo_vehiculo_inicial_id) {
                Vehiculo::query()->create([
                    'edificio_id' => $edificio->id,
                    'tipo_vehiculo_id' => $tipoEdificio->tipo_vehiculo_inicial_id,
                    'estado' => EstadoVehiculo::Disponible,
                ]);
            }

            return $edificio;
        });
    }
}
