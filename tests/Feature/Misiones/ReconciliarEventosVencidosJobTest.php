<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Jobs\ProcesarRegresoVehiculoJob;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Jobs\ProcesarLlegadaVehiculoJob;
use App\Dominio\Misiones\Jobs\ReconciliarEventosVencidosJob;
use App\Dominio\Misiones\Jobs\ResolverMisionJob;
use App\Dominio\Misiones\Jobs\VencerMisionJob;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
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

    (new ReconciliarEventosVencidosJob)->handle();

    Queue::assertPushed(ProcesarLlegadaVehiculoJob::class, fn ($job) => $job->despachoId === $despacho->id);
});

test('does not re-dispatch a cancelled or already-arrived dispatch', function () {
    $vehiculo = Vehiculo::factory()->create(['estado' => EstadoVehiculo::EnElLugar]);
    Despacho::factory()->for($vehiculo)->create([
        'salida_en' => now()->subMinutes(10),
        'llegada_en' => now()->subMinute(),
    ]);

    (new ReconciliarEventosVencidosJob)->handle();

    Queue::assertNotPushed(ProcesarLlegadaVehiculoJob::class);
});

test('re-dispatches the return job for an overdue returning vehicle', function () {
    $edificio = Edificio::factory()->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::Regresando,
        'regresa_en' => now()->subMinute(),
    ]);

    (new ReconciliarEventosVencidosJob)->handle();

    Queue::assertPushed(ProcesarRegresoVehiculoJob::class, fn ($job) => $job->vehiculoId === $vehiculo->id);
});

test('re-dispatches expiry for an overdue mission', function () {
    $mision = Mision::factory()->create([
        'estado' => EstadoMision::Generada,
        'vence_en' => now()->subMinute(),
    ]);

    (new ReconciliarEventosVencidosJob)->handle();

    Queue::assertPushed(VencerMisionJob::class, fn ($job) => $job->misionId === $mision->id);
});

test('re-dispatches resolution for an overdue en-atencion mission', function () {
    $mision = Mision::factory()->create([
        'estado' => EstadoMision::EnAtencion,
        'vence_en' => now()->addMinutes(10),
        'resuelve_en' => now()->subMinute(),
    ]);

    (new ReconciliarEventosVencidosJob)->handle();

    Queue::assertPushed(ResolverMisionJob::class, fn ($job) => $job->misionId === $mision->id);
});

test('does not touch missions and vehicles that are not overdue', function () {
    Mision::factory()->create(['estado' => EstadoMision::Generada, 'vence_en' => now()->addMinutes(10)]);
    Vehiculo::factory()->create(['estado' => EstadoVehiculo::Disponible]);

    (new ReconciliarEventosVencidosJob)->handle();

    Queue::assertNotPushed(VencerMisionJob::class);
    Queue::assertNotPushed(ProcesarRegresoVehiculoJob::class);
});
