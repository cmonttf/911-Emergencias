<?php

use App\Dominio\Geo\Clientes\NominatimClient;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use Illuminate\Support\Facades\Http;

test('parses a reverse geocoding response', function () {
    Http::fake([
        '*/reverse*' => Http::response([
            'display_name' => 'Plaza de la Independencia, Concepción, Chile',
            'address' => ['city' => 'Concepción'],
        ], 200),
    ]);

    $direccion = app(NominatimClient::class)->geocodificarInversa(
        new PuntoGeografico(-36.8270, -73.0498)
    );

    expect($direccion->direccionCompleta)->toBe('Plaza de la Independencia, Concepción, Chile')
        ->and($direccion->comuna)->toBe('Concepción');
});

test('throws when reverse geocoding finds nothing', function () {
    Http::fake([
        '*/reverse*' => Http::response(['error' => 'Unable to geocode'], 200),
    ]);

    app(NominatimClient::class)->geocodificarInversa(new PuntoGeografico(-36.8270, -73.0498));
})->throws(ServicioGeoNoDisponibleException::class);

test('parses a text search response into a list of addresses', function () {
    Http::fake([
        '*/search*' => Http::response([
            [
                'display_name' => 'Panadería Don Pan, Concepción, Chile',
                'address' => ['suburb' => 'Concepción'],
                'lat' => '-36.8270',
                'lon' => '-73.0498',
            ],
        ], 200),
    ]);

    $resultados = app(NominatimClient::class)->buscarDirecciones('panadería en Concepción, Chile');

    expect($resultados)->toHaveCount(1)
        ->and($resultados[0]->comuna)->toBe('Concepción')
        ->and($resultados[0]->punto->latitud)->toBe(-36.8270);
});
