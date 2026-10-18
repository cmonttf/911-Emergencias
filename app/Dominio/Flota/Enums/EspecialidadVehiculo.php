<?php

namespace App\Dominio\Flota\Enums;

/**
 * Especialidad de un carro de Bomberos (sección "Catálogos institucionales"
 * del ERS). Las especialidades de SAMU y Carabineros se agregan en la
 * Fase 2 sin tocar esta tabla ni esta columna.
 */
enum EspecialidadVehiculo: string
{
    case Agua = 'agua';
    case Rescate = 'rescate';
    case Escala = 'escala';
    case MaterialesPeligrosos = 'materiales_peligrosos';
    case Mando = 'mando';
}
