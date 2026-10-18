<?php

namespace App\Dominio\Flota\Events;

use App\Dominio\Flota\Models\Vehiculo;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se publica cuando `ProcesarLlegadaVehiculoJob` marca un vehículo como "en
 * el lugar" de su misión.
 */
class VehiculoLlego
{
    use Dispatchable;

    public function __construct(public readonly Vehiculo $vehiculo) {}
}
