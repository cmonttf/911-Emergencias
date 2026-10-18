<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Jobs\ProcesarLlegadaVehiculoJob;
use App\Dominio\Misiones\Jobs\ResolverMisionJob;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Models\RequisitoTipoMision;
use App\Dominio\Misiones\Models\TipoMision;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function despachoEnRutaConRequisito(int $cantidadRequerida = 1): Despacho
{
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create([
        'tipo_vehiculo_id' => $vehiculo->tipo_vehiculo_id,
        'cantidad' => $cantidadRequerida,
    ]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::Generada]);

    $despacho = Despacho::factory()->for($mision)->for($vehiculo)->create();
    $vehiculo->update(['mision_id' => $mision->id]);

    return $despacho->fresh();
}

test('marks the vehicle as on scene and moves the mission to en atencion when requirements are met', function () {
    $despacho = despachoEnRutaConRequisito(cantidadRequerida: 1);

    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle();

    $despacho->vehiculo->refresh();
    expect($despacho->vehiculo->estado)->toBe(EstadoVehiculo::EnElLugar);

    $despacho->mision->refresh();
    expect($despacho->mision->estado)->toBe(EstadoMision::EnAtencion)
        ->and($despacho->mision->resuelve_en)->not->toBeNull();

    Queue::assertPushed(ResolverMisionJob::class, fn ($job) => $job->misionId === $despacho->mision_id);
});

test('leaves the mission generada when requirements are still missing', function () {
    $despacho = despachoEnRutaConRequisito(cantidadRequerida: 2);

    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle();

    $despacho->mision->refresh();
    expect($despacho->mision->estado)->toBe(EstadoMision::Generada);

    Queue::assertNotPushed(ResolverMisionJob::class);
});

test('is idempotent when run twice', function () {
    $despacho = despachoEnRutaConRequisito(cantidadRequerida: 1);

    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle();
    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle();

    $despacho->mision->refresh();
    expect($despacho->mision->estado)->toBe(EstadoMision::EnAtencion);

    Queue::assertPushed(ResolverMisionJob::class, 1);
});

test('does nothing when the dispatch was already cancelled', function () {
    $despacho = despachoEnRutaConRequisito();
    $despacho->update(['cancelado_en' => now()]);
    $despacho->vehiculo->update(['estado' => EstadoVehiculo::Regresando]);

    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle();

    $despacho->vehiculo->refresh();
    expect($despacho->vehiculo->estado)->toBe(EstadoVehiculo::Regresando);
});
