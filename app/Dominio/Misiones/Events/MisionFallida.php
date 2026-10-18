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
 * Se publica cuando `VencerMisionJob` marca una misión como fallida por
 * vencimiento de su tiempo límite (RF-28), sin abonar créditos.
 */
class MisionFallida implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Mision $mision) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("jugador.{$this->mision->jugador_id}");
    }

    public function broadcastAs(): string
    {
        return 'mision.fallida';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['misionId' => $this->mision->id];
    }
}
