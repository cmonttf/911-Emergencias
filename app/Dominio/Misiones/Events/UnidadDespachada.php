<?php

namespace App\Dominio\Misiones\Events;

use App\Dominio\Misiones\Models\Despacho;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se publica cuando el jugador despacha una unidad hacia una misión
 * (RF-25).
 */
class UnidadDespachada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Despacho $despacho) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("jugador.{$this->despacho->mision->jugador_id}");
    }

    public function broadcastAs(): string
    {
        return 'despacho.creado';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'despachoId' => $this->despacho->id,
            'vehiculoId' => $this->despacho->vehiculo_id,
            'misionId' => $this->despacho->mision_id,
        ];
    }
}
