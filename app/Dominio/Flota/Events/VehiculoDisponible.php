<?php

namespace App\Dominio\Flota\Events;

use App\Dominio\Flota\Models\Vehiculo;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se publica cuando un vehículo vuelve a su base y queda disponible de
 * nuevo, ya sea tras completar una misión, cancelar un despacho o que la
 * misión asignada haya fallado.
 */
class VehiculoDisponible
{
    use Dispatchable;

    public function __construct(public readonly Vehiculo $vehiculo) {}
}
