<?php

namespace App\Dominio\Geo\ValueObjects;

/**
 * Un par de coordenadas geográficas (WGS84). Es inmutable: todas las
 * operaciones devuelven un valor nuevo o un escalar, nunca modifican la
 * instancia original.
 */
final readonly class PuntoGeografico
{
    /** Radio medio de la Tierra en metros, usado por la fórmula de Haversine. */
    private const int RADIO_TIERRA_METROS = 6_371_000;

    /** Metros aproximados que mide un grado de latitud (y de longitud en el ecuador). */
    private const float METROS_POR_GRADO = 111_320.0;

    public function __construct(
        public float $latitud,
        public float $longitud,
    ) {}

    /**
     * Distancia en línea recta hacia otro punto, usando la fórmula de
     * Haversine sobre una Tierra esférica. Es una aproximación suficiente
     * para radios de unos pocos kilómetros como los de esta fase.
     */
    public function distanciaMetrosHacia(self $otro): float
    {
        $latitud1 = deg2rad($this->latitud);
        $latitud2 = deg2rad($otro->latitud);
        $deltaLongitud = deg2rad($otro->longitud - $this->longitud);

        $cosenoAngulo = min(1.0, max(-1.0,
            sin($latitud1) * sin($latitud2)
            + cos($latitud1) * cos($latitud2) * cos($deltaLongitud)
        ));

        return self::RADIO_TIERRA_METROS * acos($cosenoAngulo);
    }

    /**
     * Caja delimitadora aproximada alrededor de este punto para un radio en
     * metros, usada como prefiltro barato (sobre columnas indexadas) antes
     * de aplicar la fórmula exacta de Haversine.
     *
     * @return array{latitudMin: float, latitudMax: float, longitudMin: float, longitudMax: float}
     */
    public function cajaDelimitadora(int $radioMetros): array
    {
        $deltaLatitud = $radioMetros / self::METROS_POR_GRADO;
        $metrosPorGradoLongitud = max(self::METROS_POR_GRADO * cos(deg2rad($this->latitud)), 1.0);
        $deltaLongitud = $radioMetros / $metrosPorGradoLongitud;

        return [
            'latitudMin' => $this->latitud - $deltaLatitud,
            'latitudMax' => $this->latitud + $deltaLatitud,
            'longitudMin' => $this->longitud - $deltaLongitud,
            'longitudMax' => $this->longitud + $deltaLongitud,
        ];
    }
}
