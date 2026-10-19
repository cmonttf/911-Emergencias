<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Geo\Models\Ruta;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Actions\CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction;
use App\Dominio\Misiones\Models\Mision;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

test('sorts available vehicles of the player by estimated arrival time', function () {
    $jugador = User::factory()->create();
    $edificioCercano = Edificio::factory()->for($jugador, 'jugador')->create(['latitud' => -36.8270, 'longitud' => -73.0498]);
    $edificioLejano = Edificio::factory()->for($jugador, 'jugador')->create(['latitud' => -36.9000, 'longitud' => -73.1000]);

    $vehiculoCercano = Vehiculo::factory()->for($edificioCercano)->create(['estado' => EstadoVehiculo::Disponible]);
    $vehiculoLejano = Vehiculo::factory()->for($edificioLejano)->create(['estado' => EstadoVehiculo::Disponible]);
    Vehiculo::factory()->for($edificioCercano)->create(['estado' => EstadoVehiculo::EnRuta]);

    $mision = Mision::factory()->for($jugador, 'jugador')->create(['latitud' => -36.8275, 'longitud' => -73.0500]);

    Http::fake(function ($request) use ($edificioCercano) {
        $esOrigenCercano = str_contains($request->url(), (string) $edificioCercano->longitud);

        return Http::response(respuestaOsrm(
            [[-73.0498, -36.8270], [-73.0500, -36.8275]],
            $esOrigenCercano ? 400 : 9000,
            $esOrigenCercano ? 90 : 900,
        ), 200);
    });

    $resultado = app(CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction::class)->ejecutar($mision);

    expect($resultado)->toHaveCount(2)
        ->and($resultado[0]['vehiculo']->id)->toBe($vehiculoCercano->id)
        ->and($resultado[0]['duracionSegundos'])->toBe(90)
        ->and($resultado[1]['vehiculo']->id)->toBe($vehiculoLejano->id)
        ->and($resultado[1]['duracionSegundos'])->toBe(900);
});

test('includes a returning vehicle, routed from its current position instead of its building', function () {
    Carbon::setTestNow('2026-01-01 10:05:00');

    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create(['latitud' => -36.83, 'longitud' => -73.05]);
    $rutaDeRegreso = Ruta::factory()->create(['geometria' => [[-73.05, -36.83], [-73.03, -36.81]]]);
    $vehiculo = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::Regresando,
        'ruta_id' => $rutaDeRegreso->id,
        'sale_en' => '2026-01-01 10:00:00',
        'regresa_en' => '2026-01-01 10:10:00',
    ]);

    $mision = Mision::factory()->for($jugador, 'jugador')->create();

    Http::fake([
        '*/route/v1/driving/*' => Http::response(respuestaOsrm([[0, 0], [0, 0]], 500, 60), 200),
    ]);

    $resultado = app(CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction::class)->ejecutar($mision);

    expect($resultado)->toHaveCount(1)
        ->and($resultado[0]['vehiculo']->id)->toBe($vehiculo->id);

    // A mitad de camino de su regreso (-73.04,-36.82), no en su propio
    // cuartel (-73.05,-36.83).
    Http::assertSent(fn ($request) => str_contains($request->url(), '-73.040000,-36.820000'));

    Carbon::setTestNow();
});

test('excludes vehicles belonging to other players', function () {
    $jugador = User::factory()->create();
    $otroJugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $edificioAjeno = Edificio::factory()->for($otroJugador, 'jugador')->create();

    Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::Disponible]);
    Vehiculo::factory()->for($edificioAjeno)->create(['estado' => EstadoVehiculo::Disponible]);

    $mision = Mision::factory()->for($jugador, 'jugador')->create();

    Http::fake([
        '*/route/v1/driving/*' => Http::response(respuestaOsrm([[0, 0], [0, 0]], 100, 100), 200),
    ]);

    $resultado = app(CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction::class)->ejecutar($mision);

    expect($resultado)->toHaveCount(1)
        ->and($resultado[0]['vehiculo']->edificio_id)->toBe($edificio->id);
});

test('excludes a vehicle whose route calculation fails instead of breaking the whole list', function () {
    $jugador = User::factory()->create();
    $edificioOk = Edificio::factory()->for($jugador, 'jugador')->create(['longitud' => -73.0498]);
    $edificioFalla = Edificio::factory()->for($jugador, 'jugador')->create(['longitud' => -73.0600]);

    $vehiculoOk = Vehiculo::factory()->for($edificioOk)->create(['estado' => EstadoVehiculo::Disponible]);
    Vehiculo::factory()->for($edificioFalla)->create(['estado' => EstadoVehiculo::Disponible]);

    // Coordenadas fijas (no las del rango aleatorio de la factory) para que
    // el destino de la misión nunca pueda coincidir por casualidad con la
    // longitud distintiva de $edificioFalla usada más abajo para decidir
    // qué llamada a OSRM debe fallar.
    $mision = Mision::factory()->for($jugador, 'jugador')->create(['latitud' => -36.9000, 'longitud' => -73.2000]);

    Http::fake(function ($request) use ($edificioFalla) {
        if (str_contains($request->url(), (string) $edificioFalla->longitud)) {
            return Http::response(null, 503);
        }

        return Http::response(respuestaOsrm([[0, 0], [0, 0]], 100, 100), 200);
    });

    $resultado = app(CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction::class)->ejecutar($mision);

    expect($resultado)->toHaveCount(1)
        ->and($resultado[0]['vehiculo']->id)->toBe($vehiculoOk->id);
});
