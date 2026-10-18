<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Exceptions\VehiculoNoDisponibleException;
use App\Dominio\Flota\Jobs\ProcesarRegresoVehiculoJob;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Actions\CancelarDespachoAction;
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
                'geometry' => ['coordinates' => [[-73.0450, -36.8250], [-73.0498, -36.8270]]],
                'distance' => 600,
                'duration' => 60,
            ]],
        ], 200),
    ]);
});

function despachoEnRutaAMitadDeCamino(): Despacho
{
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $mision = Mision::factory()->for($jugador, 'jugador')->create();
    $vehiculo->update(['mision_id' => $mision->id]);

    return Despacho::factory()->for($mision)->for($vehiculo)->create([
        'salida_en' => now()->subMinute(),
        'llegada_en' => now()->addMinute(),
    ]);
}

test('cancels an en-route dispatch and sends the vehicle back from its current point', function () {
    $despacho = despachoEnRutaAMitadDeCamino();

    app(CancelarDespachoAction::class)->ejecutar($despacho);

    $despacho->refresh();
    expect($despacho->cancelado_en)->not->toBeNull();

    $despacho->vehiculo->refresh();
    expect($despacho->vehiculo->estado)->toBe(EstadoVehiculo::Regresando)
        ->and($despacho->vehiculo->regresa_en)->not->toBeNull();

    Queue::assertPushed(ProcesarRegresoVehiculoJob::class, fn ($job) => $job->vehiculoId === $despacho->vehiculo_id);
});

test('throws when the vehicle is not currently en route', function () {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::Disponible]);
    $mision = Mision::factory()->for($jugador, 'jugador')->create();
    $despacho = Despacho::factory()->for($mision)->for($vehiculo)->create();

    expect(fn () => app(CancelarDespachoAction::class)->ejecutar($despacho))
        ->toThrow(VehiculoNoDisponibleException::class);
});

test('throws when the dispatch was already cancelled', function () {
    $despacho = despachoEnRutaAMitadDeCamino();
    $despacho->update(['cancelado_en' => now()]);

    expect(fn () => app(CancelarDespachoAction::class)->ejecutar($despacho))
        ->toThrow(VehiculoNoDisponibleException::class);
});
