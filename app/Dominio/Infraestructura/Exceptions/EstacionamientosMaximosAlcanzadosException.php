<?php

namespace App\Dominio\Infraestructura\Exceptions;

use RuntimeException;

/**
 * Se lanza al intentar ampliar los estacionamientos de un edificio que ya
 * llegó al tope global (`juego.estacionamientos_maximos`).
 */
class EstacionamientosMaximosAlcanzadosException extends RuntimeException {}
