<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Models\RequisitoTipoMision;
use App\Dominio\Misiones\Models\TipoMision;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('pays close to the maximum reward when completed right away', function () {
    Carbon::setTestNow('2026-01-01 10:00:00');
    $tipoMision = TipoMision::factory()->create(['creditos_min' => 250, 'creditos_max' => 500]);
    $mision = Mision::factory()->for($tipoMision)->create(['vence_en' => '2026-01-01 10:10:00']);

    expect($mision->calcularRecompensaCreditos())->toBe(500);
});

test('pays the minimum reward when it takes the whole time budget', function () {
    Carbon::setTestNow('2026-01-01 10:00:00');
    $tipoMision = TipoMision::factory()->create(['creditos_min' => 250, 'creditos_max' => 500]);
    $mision = Mision::factory()->for($tipoMision)->create(['vence_en' => '2026-01-01 10:10:00']);

    Carbon::setTestNow('2026-01-01 10:10:00');

    expect($mision->calcularRecompensaCreditos())->toBe(250);
});

test('pays a reward in between proportional to the time used', function () {
    Carbon::setTestNow('2026-01-01 10:00:00');
    $tipoMision = TipoMision::factory()->create(['creditos_min' => 250, 'creditos_max' => 500]);
    $mision = Mision::factory()->for($tipoMision)->create(['vence_en' => '2026-01-01 10:10:00']);

    // 40% del presupuesto de tiempo usado -> 60% de "factor de rapidez".
    Carbon::setTestNow('2026-01-01 10:04:00');

    expect($mision->calcularRecompensaCreditos())->toBe(400);
});

test('never pays outside the global reward bounds even if a mission type is misconfigured', function () {
    config(['juego.recompensa_mision_minima' => 250, 'juego.recompensa_mision_maxima' => 500]);
    Carbon::setTestNow('2026-01-01 10:00:00');
    $tipoMision = TipoMision::factory()->create(['creditos_min' => 100, 'creditos_max' => 1000]);
    $mision = Mision::factory()->for($tipoMision)->create(['vence_en' => '2026-01-01 10:10:00']);

    expect($mision->calcularRecompensaCreditos())->toBe(500);

    Carbon::setTestNow('2026-01-01 10:10:00');

    expect($mision->calcularRecompensaCreditos())->toBe(250);
});

test('resolution time stays the base one when present units match the requirement', function () {
    config(['juego.factor_maximo_reduccion_tiempo_resolucion' => 3]);
    $tipoMision = TipoMision::factory()->create(['tiempo_resolucion_segundos' => 300]);
    RequisitoTipoMision::factory()->for($tipoMision)->create(['cantidad' => 2]);
    $edificio = Edificio::factory()->create();
    $mision = Mision::factory()->for($tipoMision)->create();
    Vehiculo::factory()->for($edificio)->count(2)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    expect($mision->calcularTiempoResolucionSegundos())->toBe(300);
});

test('resolution time halves when double the required units are present', function () {
    config(['juego.factor_maximo_reduccion_tiempo_resolucion' => 3]);
    $tipoMision = TipoMision::factory()->create(['tiempo_resolucion_segundos' => 300]);
    RequisitoTipoMision::factory()->for($tipoMision)->create(['cantidad' => 2]);
    $edificio = Edificio::factory()->create();
    $mision = Mision::factory()->for($tipoMision)->create();
    Vehiculo::factory()->for($edificio)->count(4)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    expect($mision->calcularTiempoResolucionSegundos())->toBe(150);
});

test('resolution time reduction is capped at the configured maximum factor', function () {
    config(['juego.factor_maximo_reduccion_tiempo_resolucion' => 3]);
    $tipoMision = TipoMision::factory()->create(['tiempo_resolucion_segundos' => 300]);
    RequisitoTipoMision::factory()->for($tipoMision)->create(['cantidad' => 1]);
    $edificio = Edificio::factory()->create();
    $mision = Mision::factory()->for($tipoMision)->create();
    Vehiculo::factory()->for($edificio)->count(10)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    // El factor se topa en 3 (900s/3), no sube a 10 aunque sobren unidades.
    expect($mision->calcularTiempoResolucionSegundos())->toBe(100);
});
