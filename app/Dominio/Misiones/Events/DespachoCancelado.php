<?php

namespace App\Dominio\Misiones\Events;

use App\Dominio\Misiones\Models\Despacho;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se publica cuando el jugador cancela el despacho de una unidad en camino
 * (RF-26); la unidad pasa directo a "regresando".
 */
class DespachoCancelado
{
    use Dispatchable;

    public function __construct(public readonly Despacho $despacho) {}
}
