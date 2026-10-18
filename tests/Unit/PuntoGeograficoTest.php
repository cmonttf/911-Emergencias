<?php

use App\Dominio\Geo\ValueObjects\PuntoGeografico;

test('distance between a point and itself is zero', function () {
    $punto = new PuntoGeografico(-36.8270, -73.0498);

    expect($punto->distanciaMetrosHacia($punto))->toBe(0.0);
});

test('distance matches the known length of one degree of longitude at the equator', function () {
    $origen = new PuntoGeografico(0, 0);
    $destino = new PuntoGeografico(0, 1);

    expect($origen->distanciaMetrosHacia($destino))->toBeBetween(111_000.0, 111_400.0);
});

test('the bounding box surrounds the center point', function () {
    $centro = new PuntoGeografico(-36.8270, -73.0498);

    $caja = $centro->cajaDelimitadora(3000);

    expect($caja['latitudMin'])->toBeLessThan($centro->latitud)
        ->and($caja['latitudMax'])->toBeGreaterThan($centro->latitud)
        ->and($caja['longitudMin'])->toBeLessThan($centro->longitud)
        ->and($caja['longitudMax'])->toBeGreaterThan($centro->longitud);
});
