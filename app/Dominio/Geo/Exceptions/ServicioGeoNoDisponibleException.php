<?php

namespace App\Dominio\Geo\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando OSRM o Nominatim no responden o devuelven un resultado
 * inesperable. Al estar autoalojados, un error aquí casi siempre indica que
 * el servicio no está corriendo o que las coordenadas quedan fuera del
 * extracto de OSM cargado.
 */
class ServicioGeoNoDisponibleException extends RuntimeException {}
