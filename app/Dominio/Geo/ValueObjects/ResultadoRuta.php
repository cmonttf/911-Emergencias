<?php

namespace App\Dominio\Geo\ValueObjects;

/**
 * El resultado de pedirle una ruta a OSRM entre dos puntos.
 */
final readonly class ResultadoRuta
{
    /**
     * @param  list<array{0: float, 1: float}>  $geometria  Coordenadas `[longitud, latitud]` en el orden que recorre la ruta (formato GeoJSON de OSRM).
     */
    public function __construct(
        public array $geometria,
        public int $distanciaMetros,
        public int $duracionSegundos,
    ) {}
}
