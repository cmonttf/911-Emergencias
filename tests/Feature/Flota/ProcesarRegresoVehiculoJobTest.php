<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Events\VehiculoDisponible;
use App\Dominio\Flota\Jobs\ProcesarRegresoVehiculoJob;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Support\Facades\Event;

test('marks a returning vehicle as available and clears its mission and route', function () {
    Event::fake();

    $edificio = Edificio::factory()->create();
    $mision = Mision::factory()->create();
    $ruta = Ruta::factory()->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::Regresando,
        'mision_id' => $mision->id,
        'ruta_id' => $ruta->id,
        'regresa_en' => now()->addMinute(),
    ]);

    (new ProcesarRegresoVehiculoJob($vehiculo->id))->handle();

    $vehiculo->refresh();
    expect($vehiculo->estado)->toBe(EstadoVehiculo::Disponible)
        ->and($vehiculo->mision_id)->toBeNull()
        ->and($vehiculo->ruta_id)->toBeNull()
        ->and($vehiculo->regresa_en)->toBeNull();

    Event::assertDispatched(VehiculoDisponible::class, fn ($evento) => $evento->vehiculo->id === $vehiculo->id);
});

test('is idempotent when the vehicle is not actually returning', function () {
    Event::fake();

    $vehiculo = Vehiculo::factory()->create(['estado' => EstadoVehiculo::Disponible]);

    (new ProcesarRegresoVehiculoJob($vehiculo->id))->handle();

    Event::assertNotDispatched(VehiculoDisponible::class);
});
