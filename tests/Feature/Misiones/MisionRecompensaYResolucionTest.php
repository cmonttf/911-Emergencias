<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
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

test('resolution time is the fixed base when present units exactly match the requirement', function () {
    config(['juego.tiempo_resolucion_base_segundos' => 180, 'juego.descuento_tiempo_resolucion_por_unidad_segundos' => 20]);
    $tipoVehiculo = TipoVehiculo::factory()->create();
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create(['tipo_vehiculo_id' => $tipoVehiculo->id, 'cantidad' => 2]);
    $edificio = Edificio::factory()->create();
    $mision = Mision::factory()->for($tipoMision)->create();
    Vehiculo::factory()->for($edificio)->for($tipoVehiculo)->count(2)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    expect($mision->calcularTiempoResolucionSegundos())->toBe(180);
});

test('each extra unit of a requested type discounts a fixed amount of seconds', function () {
    config(['juego.tiempo_resolucion_base_segundos' => 180, 'juego.descuento_tiempo_resolucion_por_unidad_segundos' => 20]);
    $tipoVehiculo = TipoVehiculo::factory()->create();
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create(['tipo_vehiculo_id' => $tipoVehiculo->id, 'cantidad' => 1]);
    $edificio = Edificio::factory()->create();
    $mision = Mision::factory()->for($tipoMision)->create();
    // 2 unidades de más (3 presentes - 1 requerida) -> 180 - 2*20 = 140.
    Vehiculo::factory()->for($edificio)->for($tipoVehiculo)->count(3)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    expect($mision->calcularTiempoResolucionSegundos())->toBe(140);
});

test('resolution time reduction is floored at the configured minimum', function () {
    config([
        'juego.tiempo_resolucion_base_segundos' => 180,
        'juego.descuento_tiempo_resolucion_por_unidad_segundos' => 20,
        'juego.tiempo_resolucion_minimo_segundos' => 30,
    ]);
    $tipoVehiculo = TipoVehiculo::factory()->create();
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create(['tipo_vehiculo_id' => $tipoVehiculo->id, 'cantidad' => 1]);
    $edificio = Edificio::factory()->create();
    $mision = Mision::factory()->for($tipoMision)->create();
    // 19 unidades de más descontarían 380s, muy por debajo del piso de 30s.
    Vehiculo::factory()->for($edificio)->for($tipoVehiculo)->count(20)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    expect($mision->calcularTiempoResolucionSegundos())->toBe(30);
});

test('extra units of a type the mission does not request do not count toward the discount', function () {
    config(['juego.tiempo_resolucion_base_segundos' => 180, 'juego.descuento_tiempo_resolucion_por_unidad_segundos' => 20]);
    $tipoVehiculoSolicitado = TipoVehiculo::factory()->create();
    $tipoVehiculoAjeno = TipoVehiculo::factory()->create();
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create(['tipo_vehiculo_id' => $tipoVehiculoSolicitado->id, 'cantidad' => 1]);
    $edificio = Edificio::factory()->create();
    $mision = Mision::factory()->for($tipoMision)->create();
    Vehiculo::factory()->for($edificio)->for($tipoVehiculoSolicitado)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);
    Vehiculo::factory()->for($edificio)->for($tipoVehiculoAjeno)->count(5)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    expect($mision->calcularTiempoResolucionSegundos())->toBe(180);
});
