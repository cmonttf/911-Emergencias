<?php

use App\Dominio\Geo\Clientes\OsrmClient;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use Illuminate\Support\Facades\Http;

test('parses a successful OSRM route response', function () {
    Http::fake([
        '*/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'geometry' => ['coordinates' => [[-73.0498, -36.8270], [-73.0450, -36.8250]]],
                'distance' => 1234.5,
                'duration' => 180.4,
            ]],
        ], 200),
    ]);

    $resultado = app(OsrmClient::class)->calcularRuta(
        new PuntoGeografico(-36.8270, -73.0498),
        new PuntoGeografico(-36.8250, -73.0450),
    );

    expect($resultado->distanciaMetros)->toBe(1235)
        ->and($resultado->duracionSegundos)->toBe(180)
        ->and($resultado->geometria)->toBe([[-73.0498, -36.8270], [-73.0450, -36.8250]]);
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
