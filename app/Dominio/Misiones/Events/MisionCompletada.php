<?php

namespace App\Dominio\Misiones\Events;

use App\Dominio\Misiones\Models\Mision;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se publica cuando `ResolverMisionJob` marca una misión como completada y
 * abona sus créditos.
 */
class MisionCompletada
{
    use Dispatchable;

    public function __construct(public readonly Mision $mision) {}
}
