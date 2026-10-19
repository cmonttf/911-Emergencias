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

    public function __construct(
        private readonly string $baseUrl,
        private readonly float $velocidadExtraKmh,
    ) {}

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
                    'annotations' => 'true',
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
            duracionSegundos: $this->duracionConVelocidadDeEmergencia($ruta),
        );
    }

    /**
     * Una unidad de emergencia circula más rápido que el tránsito normal:
     * en vez de la duración "de tránsito normal" que devuelve OSRM, se
     * recalcula tramo a tramo usando la velocidad que OSRM asume para cada
     * calle (`annotation.speed`, que refleja su tipo y límite permitido)
     * más `velocidadExtraKmh`.
     *
     * @param  array{legs: list<array{annotation: array{distance: list<float>, speed: list<float>, duration: list<float>}}>}  $ruta
     */
    private function duracionConVelocidadDeEmergencia(array $ruta): int
    {
        $segundos = 0.0;

        foreach ($ruta['legs'] as $tramo) {
            $distancias = $tramo['annotation']['distance'] ?? [];
            $velocidades = $tramo['annotation']['speed'] ?? [];
            $duraciones = $tramo['annotation']['duration'] ?? [];

            foreach ($distancias as $indice => $distanciaMetros) {
                $velocidadPermitidaMetrosPorSegundo = $velocidades[$indice] ?? 0;

                if ($velocidadPermitidaMetrosPorSegundo <= 0) {
                    $segundos += $duraciones[$indice] ?? 0;

                    continue;
                }

                $velocidadEmergenciaKmh = ($velocidadPermitidaMetrosPorSegundo * 3.6) + $this->velocidadExtraKmh;
                $velocidadEmergenciaMetrosPorSegundo = $velocidadEmergenciaKmh / 3.6;

                $segundos += $distanciaMetros / $velocidadEmergenciaMetrosPorSegundo;
            }
        }

        return (int) round($segundos);
    }
}
