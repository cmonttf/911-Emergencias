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

test('an extra unit arriving after requirements are already met speeds up resolution', function () {
    config(['juego.factor_maximo_reduccion_tiempo_resolucion' => 3]);

    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo1 = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $tipoMision = TipoMision::factory()->create(['tiempo_resolucion_segundos' => 300]);
    RequisitoTipoMision::factory()->for($tipoMision)->create([
        'tipo_vehiculo_id' => $vehiculo1->tipo_vehiculo_id,
        'cantidad' => 1,
    ]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::Generada]);
    $vehiculo1->update(['mision_id' => $mision->id]);

    $despacho1 = Despacho::factory()->for($mision)->for($vehiculo1)->create();

    (new ProcesarLlegadaVehiculoJob($despacho1->id))->handle();

    $mision->refresh();
    expect($mision->estado)->toBe(EstadoMision::EnAtencion);
    $primerResuelveEn = $mision->resuelve_en;

    // Una segunda unidad que ya no era necesaria para cubrir el requisito.
    $vehiculo2 = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta, 'mision_id' => $mision->id]);
    $despacho2 = Despacho::factory()->for($mision)->for($vehiculo2)->create();

    (new ProcesarLlegadaVehiculoJob($despacho2->id))->handle();

    $mision->refresh();
    expect($mision->resuelve_en->lessThan($primerResuelveEn))->toBeTrue();

    Queue::assertPushed(ResolverMisionJob::class, 2);
});

test('does nothing when the dispatch was already cancelled', function () {
    $despacho = despachoEnRutaConRequisito();
    $despacho->update(['cancelado_en' => now()]);
    $despacho->vehiculo->update(['estado' => EstadoVehiculo::Regresando]);

    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle();

    $despacho->vehiculo->refresh();
    expect($despacho->vehiculo->estado)->toBe(EstadoVehiculo::Regresando);
});
