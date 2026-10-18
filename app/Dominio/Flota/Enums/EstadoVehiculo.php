<?php

namespace App\Dominio\Flota\Enums;

/**
 * Estados del vehículo (ver "Estados del vehículo" en las reglas de diseño
 * del proyecto): Disponible → EnRuta → EnElLugar → EnTraslado → Regresando →
 * Disponible, con los dos desvíos documentados allí. La Fase 1 no genera
 * pacientes ni detenidos, así que nunca llega a `EnTraslado`; el caso ya
 * existe para que la Fase 2 no tenga que tocar esta columna.
 */
enum EstadoVehiculo: string
{
    case Disponible = 'disponible';
    case EnRuta = 'en_ruta';
    case EnElLugar = 'en_el_lugar';
    case EnTraslado = 'en_traslado';
    case Regresando = 'regresando';
}
