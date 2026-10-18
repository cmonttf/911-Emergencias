<?php

namespace App\Dominio\Infraestructura\Enums;

/**
 * Instituciones del juego. Solo Bomberos tiene catálogos poblados en la
 * Fase 1; SAMU y Carabineros ya existen como valores del enum para que las
 * fases siguientes solo necesiten agregar filas de catálogo, no migrar esta
 * columna.
 */
enum Institucion: string
{
    case Bomberos = 'bomberos';
    case Samu = 'samu';
    case Carabineros = 'carabineros';
}
