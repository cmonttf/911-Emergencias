<?php

namespace App\Dominio\Flota\Events;

use App\Dominio\Flota\Models\Vehiculo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se publica cuando `ProcesarLlegadaVehiculoJob` marca un vehículo como "en
 * el lugar" de su misión.
 */
class VehiculoLlego implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Vehiculo $vehiculo) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("jugador.{$this->vehiculo->edificio->jugador_id}");
    }

    public function broadcastAs(): string
    {
        return 'vehiculo.llego';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['vehiculoId' => $this->vehiculo->id, 'misionId' => $this->vehiculo->mision_id];
    }
}
