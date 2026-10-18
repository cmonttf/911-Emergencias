<?php

namespace App\Dominio\Geo;

use App\Dominio\Geo\ValueObjects\PuntoGeografico;

/**
 * Calcula un punto intermedio de una ruta a partir de una fracción de su
 * longitud, usando la misma fórmula de interpolación que el cliente
 * (RNF-02: `f = (ahora - salida) / (llegada - salida)`). Se usa en el
 * servidor solo para saber dónde recalcular la ruta de vuelta cuando se
 * cancela un despacho a medio camino; nunca para transmitir posiciones.
 */
class InterpoladorRuta
{
    /**
     * @param  list<array{0: float, 1: float}>  $geometria  Coordenadas `[longitud, latitud]` de la ruta.
     */
    public static function puntoEnFraccion(array $geometria, float $fraccion): PuntoGeografico
    {
        $fraccion = min(1.0, max(0.0, $fraccion));

        if (count($geometria) < 2) {
            return self::aPunto($geometria[0]);
        }

        $distanciasPorTramo = [];
        $distanciaTotal = 0.0;

        for ($i = 0; $i < count($geometria) - 1; $i++) {
            $distancia = self::aPunto($geometria[$i])->distanciaMetrosHacia(self::aPunto($geometria[$i + 1]));
            $distanciasPorTramo[] = $distancia;
            $distanciaTotal += $distancia;
        }

        if ($distanciaTotal === 0.0) {
            return self::aPunto($geometria[0]);
        }

        $distanciaObjetivo = $fraccion * $distanciaTotal;
        $distanciaAcumulada = 0.0;

        foreach ($distanciasPorTramo as $i => $distanciaTramo) {
            if ($distanciaAcumulada + $distanciaTramo >= $distanciaObjetivo) {
                $avanceEnTramo = $distanciaTramo > 0
                    ? ($distanciaObjetivo - $distanciaAcumulada) / $distanciaTramo
                    : 0.0;

                [$lngInicio, $latInicio] = $geometria[$i];
                [$lngFin, $latFin] = $geometria[$i + 1];

                return new PuntoGeografico(
                    $latInicio + $avanceEnTramo * ($latFin - $latInicio),
                    $lngInicio + $avanceEnTramo * ($lngFin - $lngInicio),
                );
            }

            $distanciaAcumulada += $distanciaTramo;
        }

        return self::aPunto(end($geometria));
    }

    /**
     * @param  array{0: float, 1: float}  $coordenada  Par `[longitud, latitud]`.
     */
    private static function aPunto(array $coordenada): PuntoGeografico
    {
        return new PuntoGeografico($coordenada[1], $coordenada[0]);
    }
}
