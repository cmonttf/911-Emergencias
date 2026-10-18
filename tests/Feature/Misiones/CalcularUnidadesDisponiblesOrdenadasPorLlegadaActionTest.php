<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Actions\CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction;
use App\Dominio\Misiones\Models\Mision;
use App\Models\User;
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

        return Http::response([
            'code' => 'Ok',
            'routes' => [[
                'geometry' => ['coordinates' => [[-73.0498, -36.8270], [-73.0500, -36.8275]]],
                'distance' => $esOrigenCercano ? 400 : 9000,
                'duration' => $esOrigenCercano ? 90 : 900,
            ]],
        ], 200);
    });

    $resultado = app(CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction::class)->ejecutar($mision);

    expect($resultado)->toHaveCount(2)
        ->and($resultado[0]['vehiculo']->id)->toBe($vehiculoCercano->id)
        ->and($resultado[0]['duracionSegundos'])->toBe(90)
        ->and($resultado[1]['vehiculo']->id)->toBe($vehiculoLejano->id)
        ->and($resultado[1]['duracionSegundos'])->toBe(900);
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
        '*/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['geometry' => ['coordinates' => [[0, 0], [0, 0]]], 'distance' => 100, 'duration' => 100]],
        ], 200),
    ]);

    $resultado = app(CalcularUnidadesDisponiblesOrdenadasPorLlegadaAction::class)->ejecutar($mision);

    expect($resultado)->toHaveCount(1)
        ->and($resultado[0]['vehiculo']->edificio_id)->toBe($edificio->id);
});
