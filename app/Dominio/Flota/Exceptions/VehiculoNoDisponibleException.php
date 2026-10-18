<?php

namespace App\Dominio\Flota\Exceptions;

use RuntimeException;

/**
 * Se lanza al intentar despachar o cancelar un vehículo que no está en el
 * estado esperado para esa operación.
 */
class VehiculoNoDisponibleException extends RuntimeException {}
