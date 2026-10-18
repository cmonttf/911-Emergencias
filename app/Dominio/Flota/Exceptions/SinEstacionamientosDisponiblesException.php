<?php

namespace App\Dominio\Flota\Exceptions;

use RuntimeException;

/**
 * Se lanza al intentar comprar un vehículo para un edificio que ya ocupa
 * todos sus estacionamientos (RF-51).
 */
class SinEstacionamientosDisponiblesException extends RuntimeException {}
