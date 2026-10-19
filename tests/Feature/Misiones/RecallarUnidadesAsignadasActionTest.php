<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Actions\RecallarUnidadesAsignadasAction;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // El driver `sync` de pruebas ignora `->delay()`: sin esto,
    // ProcesarRegresoVehiculoJob se ejecutaría de inmediato y los
    // vehículos ya estarían "disponible" antes de poder comprobar que
    // pasaron por "regresando".
    Queue::fake();
});

test('a vehicle whose recall fails does not stop the others from being recalled', function () {
    $edificioOk = Edificio::factory()->create(['longitud' => -73.0498]);
    $edificioFalla = Edificio::factory()->create(['longitud' => -73.0600]);

    $mision = Mision::factory()->create(['latitud' => -36.9000, 'longitud' => -73.2000]);

    $vehiculoFalla = Vehiculo::factory()->for($edificioFalla)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);
    $vehiculoOk = Vehiculo::factory()->for($edificioOk)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    Http::fake(function ($request) use ($edificioFalla) {
        if (str_contains($request->url(), (string) $edificioFalla->longitud)) {
            return Http::response(null, 503);
        }

        return Http::response(respuestaOsrm([[0, 0], [0, 0]], 500, 90), 200);
    });

    app(RecallarUnidadesAsignadasAction::class)->ejecutar($mision);

    $vehiculoFalla->refresh();
    $vehiculoOk->refresh();
    expect($vehiculoFalla->estado)->toBe(EstadoVehiculo::EnElLugar)
        ->and($vehiculoOk->estado)->toBe(EstadoVehiculo::Regresando);
});

test('recalls both an en-route and an on-scene vehicle assigned to the same mission', function () {
    Http::fake([
        '*/route/v1/driving/*' => Http::response(respuestaOsrm([[-73.05, -36.83], [-73.04, -36.82]], 800, 90), 200),
    ]);

    $edificio = Edificio::factory()->create();
    $mision = Mision::factory()->create();

    $vehiculoEnRuta = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::EnRuta,
        'mision_id' => $mision->id,
    ]);
    Despacho::factory()->for($mision)->for($vehiculoEnRuta)->create([
        'salida_en' => now()->subMinute(),
        'llegada_en' => now()->addMinutes(5),
    ]);

    $vehiculoEnElLugar = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    app(RecallarUnidadesAsignadasAction::class)->ejecutar($mision);

    expect($vehiculoEnRuta->fresh()->estado)->toBe(EstadoVehiculo::Regresando)
        ->and($vehiculoEnElLugar->fresh()->estado)->toBe(EstadoVehiculo::Regresando);
});
