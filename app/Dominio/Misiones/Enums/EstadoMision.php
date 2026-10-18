<?php

namespace App\Dominio\Misiones\Enums;

/**
 * Estados de la misión en la Fase 1: Generada → EnAtencion → Completada |
 * Fallida. El estado `Escalada` (misión que sube de exigencia por falta de
 * atención) se agrega en la Fase 3; no existe todavía.
 */
enum EstadoMision: string
{
    case Generada = 'generada';
    case EnAtencion = 'en_atencion';
    case Completada = 'completada';
    case Fallida = 'fallida';
}
