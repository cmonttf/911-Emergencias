<?php

namespace App\Dominio\Misiones\Events;

use App\Dominio\Misiones\Models\Mision;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se publica cuando `VencerMisionJob` marca una misión como fallida por
 * vencimiento de su tiempo límite (RF-28), sin abonar créditos.
 */
class MisionFallida
{
    use Dispatchable;

    public function __construct(public readonly Mision $mision) {}
}
