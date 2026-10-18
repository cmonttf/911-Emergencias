<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Canal privado de un jugador: misiones y vehículos solo avisan cambios a su
 * propio dueño (RNF-01, el servidor decide quién ve qué).
 */
Broadcast::channel('jugador.{jugadorId}', function (User $user, int $jugadorId) {
    return $user->id === $jugadorId;
});
