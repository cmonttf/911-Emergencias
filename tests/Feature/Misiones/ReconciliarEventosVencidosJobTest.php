<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Jobs\ProcesarRegresoVehiculoJob;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Actions\RecallarUnidadesAsignadasAction;
use App\Dominio\Misiones\Actions\RegistrarLlegadaUnidadSolicitadaAction;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Jobs\ProcesarLlegadaVehiculoJob;
use App\Dominio\Misiones\Jobs\ReconciliarEventosVencidosJob;
use App\Dominio\Misiones\Jobs\ResolverMisionJob;
use App\Dominio\Misiones\Jobs\VencerMisionJob;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Models\RequisitoTipoMision;
use App\Dominio\Misiones\Models\TipoMision;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('re-dispatches arrival for an overdue dispatch still en route', function () {
    $vehiculo = Vehiculo::factory()->create(['estado' => EstadoVehiculo::EnRuta]);
    $despacho = Despacho::factory()->for($vehiculo)->create([
        'salida_en' => now()->subMinutes(10),
        'llegada_en' => now()->subMinute(),
    ]);

    (new ReconciliarEventosVencidosJob)->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class), app(RecallarUnidadesAsignadasAction::class));

    Queue::assertPushed(ProcesarLlegadaVehiculoJob::class, fn ($job) => $job->despachoId === $despacho->id);
});

test('does not re-dispatch a cancelled or already-arrived dispatch', function () {
    $vehiculo = Vehiculo::factory()->create(['estado' => EstadoVehiculo::EnElLugar]);
    Despacho::factory()->for($vehiculo)->create([
        'salida_en' => now()->subMinutes(10),
        'llegada_en' => now()->subMinute(),
    ]);

    (new ReconciliarEventosVencidosJob)->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class), app(RecallarUnidadesAsignadasAction::class));

    Queue::assertNotPushed(ProcesarLlegadaVehiculoJob::class);
});

test('re-dispatches the return job for an overdue returning vehicle', function () {
    $edificio = Edificio::factory()->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::Regresando,
        'regresa_en' => now()->subMinute(),
    ]);

    (new ReconciliarEventosVencidosJob)->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class), app(RecallarUnidadesAsignadasAction::class));

    Queue::assertPushed(ProcesarRegresoVehiculoJob::class, fn ($job) => $job->vehiculoId === $vehiculo->id);
});

test('re-dispatches expiry for an overdue mission', function () {
    $mision = Mision::factory()->create([
        'estado' => EstadoMision::Generada,
        'vence_en' => now()->subMinute(),
    ]);

    (new ReconciliarEventosVencidosJob)->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class), app(RecallarUnidadesAsignadasAction::class));

    Queue::assertPushed(VencerMisionJob::class, fn ($job) => $job->misionId === $mision->id);
});

test('re-dispatches resolution for an overdue en-atencion mission', function () {
    $mision = Mision::factory()->create([
        'estado' => EstadoMision::EnAtencion,
        'vence_en' => now()->addMinutes(10),
        'resuelve_en' => now()->subMinute(),
    ]);

    (new ReconciliarEventosVencidosJob)->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class), app(RecallarUnidadesAsignadasAction::class));

    Queue::assertPushed(ResolverMisionJob::class, fn ($job) => $job->misionId === $mision->id);
});

test('fixes a mission stuck generada whose unit is already on scene but was never processed', function () {
    $edificio = Edificio::factory()->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnElLugar]);
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create([
        'tipo_vehiculo_id' => $vehiculo->tipo_vehiculo_id,
        'cantidad' => 1,
    ]);
    // Simula un ProcesarLlegadaVehiculoJob que se cayó justo después de
    // pasar el vehículo a "en el lugar" pero antes de actualizar la misión:
    // queda cubierta pero sin resuelve_en ni ResolverMisionJob agendado.
    $mision = Mision::factory()->for($tipoMision)->create(['estado' => EstadoMision::Generada]);
    $vehiculo->update(['mision_id' => $mision->id]);

    (new ReconciliarEventosVencidosJob)->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class), app(RecallarUnidadesAsignadasAction::class));

    $mision->refresh();
    expect($mision->estado)->toBe(EstadoMision::EnAtencion)
        ->and($mision->resuelve_en)->not->toBeNull();

    Queue::assertPushed(ResolverMisionJob::class, fn ($job) => $job->misionId === $mision->id);
});

test('recalls a vehicle still assigned to an already-finished mission', function () {
    // Simula una unidad que quedó huérfana porque OSRM falló justo cuando
    // se intentó recallarla al resolver o vencer su misión.
    Http::fake([
        '*/route/v1/driving/*' => Http::response(respuestaOsrm([[-73.05, -36.83], [-73.04, -36.82]], 800, 90), 200),
    ]);

    $edificio = Edificio::factory()->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnElLugar]);
    $mision = Mision::factory()->create(['estado' => EstadoMision::Completada]);
    $vehiculo->update(['mision_id' => $mision->id]);

    (new ReconciliarEventosVencidosJob)->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class), app(RecallarUnidadesAsignadasAction::class));

    $vehiculo->refresh();
    expect($vehiculo->estado)->toBe(EstadoVehiculo::Regresando);
    Queue::assertPushed(ProcesarRegresoVehiculoJob::class, fn ($job) => $job->vehiculoId === $vehiculo->id);
});

test('does not touch missions and vehicles that are not overdue', function () {
    Mision::factory()->create(['estado' => EstadoMision::Generada, 'vence_en' => now()->addMinutes(10)]);
    Vehiculo::factory()->create(['estado' => EstadoVehiculo::Disponible]);

    (new ReconciliarEventosVencidosJob)->handle(app(RegistrarLlegadaUnidadSolicitadaAction::class), app(RecallarUnidadesAsignadasAction::class));

    Queue::assertNotPushed(VencerMisionJob::class);
    Queue::assertNotPushed(ProcesarRegresoVehiculoJob::class);
});
