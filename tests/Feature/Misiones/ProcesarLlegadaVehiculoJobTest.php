<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Actions\RegistrarLlegadaUnidadSolicitadaAction;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Jobs\ProcesarLlegadaVehiculoJob;
use App\Dominio\Misiones\Jobs\ResolverMisionJob;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Models\RequisitoTipoMision;
use App\Dominio\Misiones\Models\TipoMision;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

afterEach(function () {
    Carbon::setTestNow();
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

    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class));

    $despacho->vehiculo->refresh();
    expect($despacho->vehiculo->estado)->toBe(EstadoVehiculo::EnElLugar);

    $despacho->mision->refresh();
    expect($despacho->mision->estado)->toBe(EstadoMision::EnAtencion)
        ->and($despacho->mision->resuelve_en)->not->toBeNull();

    Queue::assertPushed(ResolverMisionJob::class, fn ($job) => $job->misionId === $despacho->mision_id);
});

test('enters en atencion with extra time per missing unit when requirements are not fully met yet', function () {
    config(['juego.tiempo_resolucion_base_segundos' => 180, 'juego.tiempo_extra_por_unidad_faltante_segundos' => 60]);
    Carbon::setTestNow('2026-01-01 10:00:00');

    // Pide 2 unidades del tipo, pero solo llega 1: falta 1.
    $despacho = despachoEnRutaConRequisito(cantidadRequerida: 2);

    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class));

    $despacho->mision->refresh();
    expect($despacho->mision->estado)->toBe(EstadoMision::EnAtencion)
        // 180s base + 60s por la unidad que falta = 240s -> 10:04:00.
        ->and($despacho->mision->resuelve_en->toDateTimeString())->toBe('2026-01-01 10:04:00');

    Queue::assertPushed(ResolverMisionJob::class, fn ($job) => $job->misionId === $despacho->mision_id);
});

test('cuts the remaining time by the configured percentage when the missing units finally arrive', function () {
    config([
        'juego.tiempo_resolucion_base_segundos' => 180,
        'juego.tiempo_extra_por_unidad_faltante_segundos' => 60,
        'juego.descuento_al_completar_cobertura' => 0.3,
    ]);
    Carbon::setTestNow('2026-01-01 10:00:00');

    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo1 = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create([
        'tipo_vehiculo_id' => $vehiculo1->tipo_vehiculo_id,
        'cantidad' => 2,
    ]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::Generada]);
    $vehiculo1->update(['mision_id' => $mision->id]);
    $despacho1 = Despacho::factory()->for($mision)->for($vehiculo1)->create();

    (new ProcesarLlegadaVehiculoJob($despacho1->id))->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class));

    $mision->refresh();
    // Falta 1 de 2: 180 + 60 = 240s -> resuelve a las 10:04:00.
    expect($mision->resuelve_en->toDateTimeString())->toBe('2026-01-01 10:04:00');

    // Pasan 100s reales (quedan 140 de los 240 originales) y llega la
    // segunda unidad requerida, completando la cobertura por primera vez.
    Carbon::setTestNow('2026-01-01 10:01:40');

    $vehiculo2 = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::EnRuta,
        'tipo_vehiculo_id' => $vehiculo1->tipo_vehiculo_id,
        'mision_id' => $mision->id,
    ]);
    $despacho2 = Despacho::factory()->for($mision)->for($vehiculo2)->create();

    (new ProcesarLlegadaVehiculoJob($despacho2->id))->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class));

    $mision->refresh();
    // Quedaban 140s; se recortan un 30% -> 98s desde las 10:01:40 -> 10:03:18.
    expect($mision->resuelve_en->toDateTimeString())->toBe('2026-01-01 10:03:18');

    Queue::assertPushed(ResolverMisionJob::class, 2);
});

test('is idempotent when run twice', function () {
    $despacho = despachoEnRutaConRequisito(cantidadRequerida: 1);

    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class));
    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class));

    $despacho->mision->refresh();
    expect($despacho->mision->estado)->toBe(EstadoMision::EnAtencion);

    Queue::assertPushed(ResolverMisionJob::class, 1);
});

test('an extra unit of the requested type arriving after requirements are already met speeds up resolution', function () {
    config(['juego.tiempo_resolucion_base_segundos' => 180, 'juego.descuento_tiempo_resolucion_por_unidad_segundos' => 20]);

    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo1 = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create([
        'tipo_vehiculo_id' => $vehiculo1->tipo_vehiculo_id,
        'cantidad' => 1,
    ]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::Generada]);
    $vehiculo1->update(['mision_id' => $mision->id]);

    $despacho1 = Despacho::factory()->for($mision)->for($vehiculo1)->create();

    (new ProcesarLlegadaVehiculoJob($despacho1->id))->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class));

    $mision->refresh();
    expect($mision->estado)->toBe(EstadoMision::EnAtencion);
    $primerResuelveEn = $mision->resuelve_en;

    // Una segunda unidad del mismo tipo solicitado, que ya no era necesaria
    // para cubrir el requisito (cantidad => 1).
    $vehiculo2 = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::EnRuta,
        'tipo_vehiculo_id' => $vehiculo1->tipo_vehiculo_id,
        'mision_id' => $mision->id,
    ]);
    $despacho2 = Despacho::factory()->for($mision)->for($vehiculo2)->create();

    (new ProcesarLlegadaVehiculoJob($despacho2->id))->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class));

    $mision->refresh();
    expect($mision->resuelve_en->lessThan($primerResuelveEn))->toBeTrue();

    Queue::assertPushed(ResolverMisionJob::class, 2);
});

test('does nothing when the dispatch was already cancelled', function () {
    $despacho = despachoEnRutaConRequisito();
    $despacho->update(['cancelado_en' => now()]);
    $despacho->vehiculo->update(['estado' => EstadoVehiculo::Regresando]);

    (new ProcesarLlegadaVehiculoJob($despacho->id))->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class));

    $despacho->vehiculo->refresh();
    expect($despacho->vehiculo->estado)->toBe(EstadoVehiculo::Regresando);
});
