<?php

namespace App\Dominio\Geo\Clientes;

use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\ValueObjects\DireccionGeocodificada;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use Illuminate\Support\Facades\Http;

/**
 * Cliente del servicio Nominatim autoalojado. Convierte coordenadas en
 * direcciones chilenas (RF: dirección real al construir un edificio) y
 * busca direcciones reales por texto para importar puntos de interés.
 */
class NominatimClient
{
    public function __construct(private readonly string $baseUrl) {}

    /**
     * Geocodificación inversa: obtiene la dirección legible de un punto del
     * mapa, usada al construir un edificio.
     *
     * @throws ServicioGeoNoDisponibleException cuando Nominatim no responde o no reconoce el punto.
     */
    public function geocodificarInversa(PuntoGeografico $punto): DireccionGeocodificada
    {
        $respuesta = Http::baseUrl($this->baseUrl)->get('reverse', [
            'lat' => $punto->latitud,
            'lon' => $punto->longitud,
            'format' => 'jsonv2',
            'addressdetails' => 1,
        ]);

        $cuerpo = $respuesta->json();

        if (! $respuesta->successful() || empty($cuerpo['display_name'])) {
            throw new ServicioGeoNoDisponibleException(
                "Nominatim no pudo geocodificar el punto ({$punto->latitud}, {$punto->longitud})."
            );
        }

        return new DireccionGeocodificada(
            direccionCompleta: $cuerpo['display_name'],
            comuna: $this->extraerComuna($cuerpo['address'] ?? []),
            punto: $punto,
        );
    }

    /**
     * Búsqueda por texto dentro de Chile, usada para importar direcciones
     * reales como puntos de interés (comando `puntos-interes:importar`).
     *
     * @return list<DireccionGeocodificada>
     *
     * @throws ServicioGeoNoDisponibleException cuando Nominatim no responde.
     */
    public function buscarDirecciones(string $consulta, int $limite = 20): array
    {
        $respuesta = Http::baseUrl($this->baseUrl)->get('search', [
            'q' => $consulta,
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'countrycodes' => 'cl',
            'limit' => $limite,
        ]);

        if (! $respuesta->successful()) {
            throw new ServicioGeoNoDisponibleException(
                "Nominatim no pudo resolver la búsqueda «{$consulta}» (código: {$respuesta->status()})."
            );
        }

        return collect($respuesta->json())
            ->map(fn (array $resultado) => new DireccionGeocodificada(
                direccionCompleta: $resultado['display_name'],
                comuna: $this->extraerComuna($resultado['address'] ?? []),
                punto: new PuntoGeografico((float) $resultado['lat'], (float) $resultado['lon']),
            ))
            ->all();
    }

    /**
     * Nominatim no tiene un campo único de "comuna": según la zona viene en
     * `city`, `town`, `municipality` o `suburb`.
     *
     * @param  array<string, string>  $direccion
     */
    private function extraerComuna(array $direccion): ?string
    {
        return $direccion['city']
            ?? $direccion['town']
            ?? $direccion['municipality']
            ?? $direccion['suburb']
            ?? null;
    }
}
