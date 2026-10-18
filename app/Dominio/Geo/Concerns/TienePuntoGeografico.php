<?php

namespace App\Dominio\Geo\Concerns;

use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use Illuminate\Database\Eloquent\Builder;

/**
 * Para modelos con columnas `latitud`/`longitud`. Da acceso al punto como
 * value object y un scope para filtrar por radio sin depender de un tipo de
 * dato espacial (ver decisión técnica en el plan de la Fase 1).
 */
trait TienePuntoGeografico
{
    public function puntoGeografico(): PuntoGeografico
    {
        return new PuntoGeografico((float) $this->latitud, (float) $this->longitud);
    }

    /**
     * Filtra las filas dentro de un radio (en metros) alrededor de un punto.
     *
     * Primero acota con una caja delimitadora sobre `latitud`/`longitud`
     * (aprovechable por un índice compuesto) y luego aplica la fórmula
     * exacta de Haversine para descartar las esquinas de la caja que
     * quedan fuera del radio real.
     */
    public function scopeDentroDelRadio(Builder $query, PuntoGeografico $centro, int $radioMetros): Builder
    {
        $caja = $centro->cajaDelimitadora($radioMetros);

        return $query
            ->whereBetween('latitud', [$caja['latitudMin'], $caja['latitudMax']])
            ->whereBetween('longitud', [$caja['longitudMin'], $caja['longitudMax']])
            ->whereRaw(
                '(? * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(latitud)) * COS(RADIANS(longitud) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(latitud))))) <= ?',
                [6_371_000, $centro->latitud, $centro->longitud, $centro->latitud, $radioMetros]
            );
    }
}
