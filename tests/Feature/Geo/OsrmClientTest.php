<?php

use App\Dominio\Geo\Clientes\OsrmClient;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use Illuminate\Support\Facades\Http;

test('parses a successful OSRM route response, adjusting speed for emergency vehicles', function () {
    config(['juego.osrm.velocidad_extra_kmh' => 10]);

    Http::fake([
        '*/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'geometry' => ['coordinates' => [[-73.0498, -36.8270], [-73.0450, -36.8250]]],
                'distance' => 1234.5,
                'duration' => 180.4,
                'legs' => [[
                    'annotation' => [
                        // 20 m/s (72 km/h) de "velocidad permitida" según OSRM.
                        'distance' => [1234.5],
                        'speed' => [20],
                        'duration' => [61.725],
                    ],
                ]],
            ]],
        ], 200),
    ]);

    $resultado = app(OsrmClient::class)->calcularRuta(
        new PuntoGeografico(-36.8270, -73.0498),
        new PuntoGeografico(-36.8250, -73.0450),
    );

    // 72 km/h + 10 km/h de extra = 82 km/h ≈ 22.78 m/s → 1234.5 / 22.78 ≈ 54s.
    expect($resultado->distanciaMetros)->toBe(1235)
        ->and($resultado->duracionSegundos)->toBe(54)
        ->and($resultado->geometria)->toBe([[-73.0498, -36.8270], [-73.0450, -36.8250]]);
});

test('falls back to OSRM own duration for a segment reported with zero speed', function () {
    config(['juego.osrm.velocidad_extra_kmh' => 10]);

    Http::fake([
        '*/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'geometry' => ['coordinates' => [[-73.0498, -36.8270], [-73.0450, -36.8250]]],
                'distance' => 0,
                'duration' => 5,
                'legs' => [[
                    'annotation' => [
                        'distance' => [0],
                        'speed' => [0],
                        'duration' => [5],
                    ],
                ]],
            ]],
        ], 200),
    ]);

    $resultado = app(OsrmClient::class)->calcularRuta(
        new PuntoGeografico(-36.8270, -73.0498),
        new PuntoGeografico(-36.8250, -73.0450),
    );

    expect($resultado->duracionSegundos)->toBe(5);
});

test('throws when OSRM cannot find a route', function () {
    Http::fake([
        '*/route/v1/driving/*' => Http::response(['code' => 'NoRoute', 'routes' => []], 200),
    ]);

    app(OsrmClient::class)->calcularRuta(
        new PuntoGeografico(-36.8270, -73.0498),
        new PuntoGeografico(-36.8250, -73.0450),
    );
})->throws(ServicioGeoNoDisponibleException::class);

test('throws when OSRM is unreachable', function () {
    Http::fake([
        '*/route/v1/driving/*' => Http::response(null, 500),
    ]);

    app(OsrmClient::class)->calcularRuta(
        new PuntoGeografico(-36.8270, -73.0498),
        new PuntoGeografico(-36.8250, -73.0450),
    );
})->throws(ServicioGeoNoDisponibleException::class);

test('throws a domain exception instead of a raw connection exception', function () {
    Http::fake([
        '*/route/v1/driving/*' => Http::failedConnection(),
    ]);

    app(OsrmClient::class)->calcularRuta(
        new PuntoGeografico(-36.8270, -73.0498),
        new PuntoGeografico(-36.8250, -73.0450),
    );
})->throws(ServicioGeoNoDisponibleException::class);
