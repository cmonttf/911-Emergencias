<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Exceptions\VehiculoNoDisponibleException;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Actions\DespacharUnidadesAMisionAction;
use App\Dominio\Misiones\Jobs\ProcesarLlegadaVehiculoJob;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Http::fake([
        '*/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'geometry' => ['coordinates' => [[-73.0498, -36.8270], [-73.0450, -36.8250]]],
                'distance' => 1500,
                'duration' => 200,
            ]],
        ], 200),
    ]);
});

test('dispatches an available vehicle and schedules its arrival', function () {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::Disponible]);
    $mision = Mision::factory()->for($jugador, 'jugador')->create();

    $despacho = app(DespacharUnidadesAMisionAction::class)->ejecutar($vehiculo, $mision);

    expect($despacho)->toBeInstanceOf(Despacho::class)
        ->and($despacho->mision_id)->toBe($mision->id)
        ->and($despacho->ruta->distancia_m)->toBe(1500);

    $vehiculo->refresh();
    expect($vehiculo->estado)->toBe(EstadoVehiculo::EnRuta)
        ->and($vehiculo->mision_id)->toBe($mision->id);

    Queue::assertPushed(ProcesarLlegadaVehiculoJob::class, fn ($job) => $job->despachoId === $despacho->id);
});

test('throws when the vehicle is not available and creates no dispatch', function () {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $mision = Mision::factory()->for($jugador, 'jugador')->create();

    expect(fn () => app(DespacharUnidadesAMisionAction::class)->ejecutar($vehiculo, $mision))
        ->toThrow(VehiculoNoDisponibleException::class);

    expect(Despacho::count())->toBe(0);
});
