<?php

namespace App\Dominio\Misiones\Support;

/**
 * Escala segundos de juego a segundos reales según
 * `config('juego.factor_velocidad_tiempo')`. Un factor mayor a 1 acelera el
 * juego (acorta la espera real); se usa tanto para los tiempos de misión
 * como para la duración de los viajes de los vehículos.
 */
final class FactorVelocidadJuego
{
    public static function escalarSegundos(int $segundosBase): int
    {
        return (int) round($segundosBase / config('juego.factor_velocidad_tiempo'));
    }
}
