<?php

namespace App\Dominio\Misiones\Events;

use App\Dominio\Misiones\Models\Mision;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se publica cuando el scheduler crea una misión nueva para un jugador
 * (RF-20). La Fase 1 no transmite esto todavía por Reverb (eso llega en el
 * incremento de tiempo real); por ahora solo es un punto de extensión para
 * quien quiera reaccionar a nuevas misiones sin acoplarse a `GenerarMisionesJob`.
 */
class MisionGenerada
{
    use Dispatchable;

    public function __construct(public readonly Mision $mision) {}
}
