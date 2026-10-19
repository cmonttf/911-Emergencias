<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('an available vehicle is at its own building', function () {
    $edificio = Edificio::factory()->create(['latitud' => -36.8270, 'longitud' => -73.0498]);
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::Disponible]);

    $posicion = $vehiculo->posicionActual();

    expect($posicion->latitud)->toBe(-36.8270)
        ->and($posicion->longitud)->toBe(-73.0498);
});

test('a returning vehicle is interpolated over its own return leg, not at its building', function () {
    Carbon::setTestNow('2026-01-01 10:05:00');

    $edificio = Edificio::factory()->create(['latitud' => -36.83, 'longitud' => -73.05]);
    $ruta = Ruta::factory()->create(['geometria' => [[-73.05, -36.83], [-73.03, -36.81]]]);
    $vehiculo = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::Regresando,
        'ruta_id' => $ruta->id,
        'sale_en' => '2026-01-01 10:00:00',
        'regresa_en' => '2026-01-01 10:10:00',
    ]);

    // A mitad de camino (5 de 10 min) entre [-73.05,-36.83] y [-73.03,-36.81].
    $posicion = $vehiculo->posicionActual();

    expect(round($posicion->latitud, 3))->toBe(-36.82)
        ->and(round($posicion->longitud, 3))->toBe(-73.04);
});

test('an en-route vehicle is interpolated over its active dispatch', function () {
    Carbon::setTestNow('2026-01-01 10:03:00');

    $edificio = Edificio::factory()->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $mision = Mision::factory()->create();
    $ruta = Ruta::factory()->create(['geometria' => [[-73.05, -36.83], [-73.01, -36.79]]]);
    Despacho::factory()->for($mision)->for($vehiculo)->for($ruta)->create([
        'salida_en' => '2026-01-01 10:00:00',
        'llegada_en' => '2026-01-01 10:05:00',
    ]);

    // A 3 de 5 min (60%) entre [-73.05,-36.83] y [-73.01,-36.79].
    $posicion = $vehiculo->posicionActual();

    expect(round($posicion->latitud, 3))->toBe(-36.806)
        ->and(round($posicion->longitud, 3))->toBe(-73.026);
});
