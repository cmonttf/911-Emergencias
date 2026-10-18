<?php

namespace App\Dominio\Geo\ValueObjects;

/**
 * Una dirección resuelta por Nominatim, ya sea por búsqueda directa o por
 * geocodificación inversa de un punto del mapa.
 */
final readonly class DireccionGeocodificada
{
    public function __construct(
        public string $direccionCompleta,
        public ?string $comuna,
        public PuntoGeografico $punto,
    ) {}
}
