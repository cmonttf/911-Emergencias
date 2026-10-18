<?php

use App\Dominio\Flota\Events\VehiculoDisponible;
use App\Dominio\Flota\Events\VehiculoLlego;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Events\DespachoCancelado;
use App\Dominio\Misiones\Events\MisionCompletada;
use App\Dominio\Misiones\Events\MisionFallida;
use App\Dominio\Misiones\Events\MisionGenerada;
use App\Dominio\Misiones\Events\UnidadDespachada;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

test('mission events broadcast on their players private channel', function (string $eventoClase, string $nombreEvento) {
    $jugador = User::factory()->create();
    $mision = Mision::factory()->for($jugador, 'jugador')->create();

    $evento = new $eventoClase($mision);

    expect($evento)->toBeInstanceOf(ShouldBroadcast::class);
    expect($evento->broadcastOn())->toBeInstanceOf(PrivateChannel::class);
    expect($evento->broadcastOn()->name)->toBe("private-jugador.{$jugador->id}");
    expect($evento->broadcastAs())->toBe($nombreEvento);
    expect($evento->broadcastWith())->toBe(['misionId' => $mision->id]);
})->with([
    [MisionGenerada::class, 'mision.generada'],
    [MisionCompletada::class, 'mision.completada'],
    [MisionFallida::class, 'mision.fallida'],
]);

test('dispatch events broadcast on the missions players private channel', function (string $eventoClase, string $nombreEvento) {
    $jugador = User::factory()->create();
    $mision = Mision::factory()->for($jugador, 'jugador')->create();
    $despacho = Despacho::factory()->for($mision)->create();

    $evento = new $eventoClase($despacho);

    expect($evento->broadcastOn()->name)->toBe("private-jugador.{$jugador->id}")
        ->and($evento->broadcastAs())->toBe($nombreEvento);
})->with([
    [UnidadDespachada::class, 'despacho.creado'],
    [DespachoCancelado::class, 'despacho.cancelado'],
]);

test('vehicle events broadcast on the owning players private channel', function (string $eventoClase, string $nombreEvento) {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create();

    $evento = new $eventoClase($vehiculo);

    expect($evento->broadcastOn()->name)->toBe("private-jugador.{$jugador->id}")
        ->and($evento->broadcastAs())->toBe($nombreEvento);
})->with([
    [VehiculoLlego::class, 'vehiculo.llego'],
    [VehiculoDisponible::class, 'vehiculo.disponible'],
]);

/**
 * El driver `null` de pruebas no ejecuta ninguna lógica de autorización real
 * (`NullBroadcaster::auth()` es un no-op), así que se fuerza el driver
 * `reverb` para que routes/channels.php realmente se evalúe; no requiere una
 * conexión de red real, la autorización de canales privados se firma
 * localmente. `Broadcast::channel()` registra sus canales contra el driver
 * por defecto vigente en el momento del registro, así que hay que volver a
 * cargar routes/channels.php después de cambiar el driver para que las
 * registre contra la instancia nueva.
 */
function forzarDriverReverbParaCanales(): void
{
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
}

test('a player can authorize their own private channel', function () {
    forzarDriverReverbParaCanales();
    $jugador = User::factory()->create();

    $this->actingAs($jugador)
        ->post('/broadcasting/auth', ['channel_name' => "private-jugador.{$jugador->id}", 'socket_id' => '1234.1234'])
        ->assertSuccessful();
});

test('a player cannot authorize another players private channel', function () {
    forzarDriverReverbParaCanales();
    $jugador = User::factory()->create();
    $otroJugador = User::factory()->create();

    $this->actingAs($jugador)
        ->post('/broadcasting/auth', ['channel_name' => "private-jugador.{$otroJugador->id}"])
        ->assertForbidden();
});
