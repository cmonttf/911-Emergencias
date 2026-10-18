<?php

namespace App\Dominio\Misiones\Events;

use App\Dominio\Misiones\Models\Mision;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se publica cuando el scheduler crea una misión nueva para un jugador
 * (RF-20) y se avisa al navegador por el canal privado del jugador.
 */
class MisionGenerada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Mision $mision) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("jugador.{$this->mision->jugador_id}");
    }

    public function broadcastAs(): string
    {
        return 'mision.generada';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['misionId' => $this->mision->id];
    }
}
