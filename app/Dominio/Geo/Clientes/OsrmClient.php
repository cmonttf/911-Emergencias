<?php

namespace App\Dominio\Geo\Clientes;

use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use App\Dominio\Geo\ValueObjects\ResultadoRuta;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente del servicio OSRM autoalojado. Calcula rutas reales por calles
 * entre dos puntos para el despacho de unidades (RF-11, RF-24).
 */
class OsrmClient
{
    /** Identifica la app ante el servidor de demostración público de OSRM. */
    private const string USER_AGENT = 'CentralDeEmergenciasChile/1.0 (proyecto educativo)';

    public function __construct(private readonly string $baseUrl) {}

    /**
     * Pide a OSRM la ruta en auto más rápida entre dos puntos.
     *
     * @throws ServicioGeoNoDisponibleException cuando OSRM no responde o no encuentra una ruta.
     */
    public function calcularRuta(PuntoGeografico $origen, PuntoGeografico $destino): ResultadoRuta
    {
        $coordenadas = sprintf(
            '%F,%F;%F,%F',
            $origen->longitud,
            $origen->latitud,
            $destino->longitud,
            $destino->latitud,
        );

        try {
            $respuesta = Http::baseUrl($this->baseUrl)
                ->withUserAgent(self::USER_AGENT)
                ->timeout(10)
                ->get("route/v1/driving/{$coordenadas}", [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                ]);
        } catch (ConnectionException $excepcion) {
            throw new ServicioGeoNoDisponibleException(
                "No se pudo conectar con OSRM en {$this->baseUrl}.", previous: $excepcion
            );
        }

        $cuerpo = $respuesta->json();

        if (! $respuesta->successful() || ($cuerpo['code'] ?? null) !== 'Ok' || empty($cuerpo['routes'])) {
            throw new ServicioGeoNoDisponibleException(
                "OSRM no pudo calcular una ruta entre los puntos indicados (código: {$respuesta->status()})."
            );
        }

        $ruta = $cuerpo['routes'][0];

        return new ResultadoRuta(
            geometria: $ruta['geometry']['coordinates'],
            distanciaMetros: (int) round($ruta['distance']),
            duracionSegundos: (int) round($ruta['duration']),
        );
    }
}
