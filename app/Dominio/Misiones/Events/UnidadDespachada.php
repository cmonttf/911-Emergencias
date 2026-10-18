<?php

namespace App\Dominio\Misiones\Events;

use App\Dominio\Misiones\Models\Despacho;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se publica cuando el jugador despacha una unidad hacia una misión
 * (RF-25).
 */
class UnidadDespachada
{
    use Dispatchable;

    public function __construct(public readonly Despacho $despacho) {}
}
