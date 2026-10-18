<?php

use App\Dominio\Geo\Models\PuntoInteres;
use Illuminate\Support\Facades\Http;

function resultadoNominatimFalso(string $nombre, float $lat, float $lon): array
{
    return [
        'display_name' => $nombre,
        'address' => ['city' => 'Concepción'],
        'lat' => (string) $lat,
        'lon' => (string) $lon,
    ];
}

test('imports real addresses into puntos_interes for each category', function () {
    Http::fake([
        '*/search*' => Http::response([
            resultadoNominatimFalso('Dirección de prueba 1, Concepción, Chile', -36.8270, -73.0498),
            resultadoNominatimFalso('Dirección de prueba 2, Concepción, Chile', -36.8280, -73.0500),
        ], 200),
    ]);

    $this->artisan('puntos-interes:importar', ['comuna' => 'Concepción'])
        ->assertSuccessful();

    // Las 4 categorías comparten las mismas 2 coordenadas de prueba, así que
    // quedan solo 2 filas: por coordenada (no por categoría) es como se
    // deduplican los puntos de interés al reimportar.
    expect(PuntoInteres::count())->toBe(2)
        ->and(PuntoInteres::first()->comuna)->toBe('Concepción');
});

test('running the import twice does not duplicate the same address', function () {
    Http::fake([
        '*/search*' => Http::response([
            resultadoNominatimFalso('Dirección de prueba, Concepción, Chile', -36.8270, -73.0498),
        ], 200),
    ]);

    $this->artisan('puntos-interes:importar', ['comuna' => 'Concepción']);
    $this->artisan('puntos-interes:importar', ['comuna' => 'Concepción']);

    expect(PuntoInteres::count())->toBe(1);
});
