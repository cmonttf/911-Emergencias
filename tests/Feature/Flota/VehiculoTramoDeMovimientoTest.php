<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;

test('an en-route vehicle takes its movement leg from the active dispatch', function () {
    $edificio = Edificio::factory()->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $mision = Mision::factory()->create();
    $ruta = Ruta::factory()->create(['geometria' => [[-73.05, -36.83], [-73.04, -36.82]]]);
    Despacho::factory()->for($mision)->for($vehiculo)->for($ruta)->create([
        'salida_en' => '2026-01-01 10:00:00',
        'llegada_en' => '2026-01-01 10:05:00',
    ]);

    $tramo = $vehiculo->tramoDeMovimiento();

    expect($tramo['geometria'])->toBe([[-73.05, -36.83], [-73.04, -36.82]])
        ->and($tramo['salidaEn'])->toStartWith('2026-01-01T10:00:00')
        ->and($tramo['llegadaEn'])->toStartWith('2026-01-01T10:05:00');
});

test('a cancelled dispatch is not used as the movement leg', function () {
    $edificio = Edificio::factory()->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $mision = Mision::factory()->create();
    Despacho::factory()->for($mision)->for($vehiculo)->create(['cancelado_en' => now()]);

    expect($vehiculo->tramoDeMovimiento())->toBeNull();
});

test('a returning vehicle takes its movement leg from its own columns', function () {
    $edificio = Edificio::factory()->create();
    $ruta = Ruta::factory()->create(['geometria' => [[-73.04, -36.82], [-73.05, -36.83]]]);
    $vehiculo = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::Regresando,
        'ruta_id' => $ruta->id,
        'sale_en' => '2026-01-01 11:00:00',
        'regresa_en' => '2026-01-01 11:03:00',
    ]);

    $tramo = $vehiculo->tramoDeMovimiento();

    expect($tramo['geometria'])->toBe([[-73.04, -36.82], [-73.05, -36.83]])
        ->and($tramo['salidaEn'])->toStartWith('2026-01-01T11:00:00')
        ->and($tramo['llegadaEn'])->toStartWith('2026-01-01T11:03:00');
});

test('an available vehicle has no movement leg', function () {
    $vehiculo = Vehiculo::factory()->create(['estado' => EstadoVehiculo::Disponible]);

    expect($vehiculo->tramoDeMovimiento())->toBeNull();
});
