<?php

use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Dominio\Misiones\Models\TipoMision;
use Database\Seeders\TipoEdificioSeeder;
use Database\Seeders\TipoMisionSeeder;
use Database\Seeders\TipoVehiculoSeeder;

beforeEach(function () {
    // Mismo orden que DatabaseSeeder: TipoVehiculoSeeder primero, porque
    // TipoEdificioSeeder necesita el carro de agua para el vehículo
    // inicial gratis del cuartel.
    $this->seed([TipoVehiculoSeeder::class, TipoEdificioSeeder::class, TipoMisionSeeder::class]);
});

test('seeds exactly one building type for Fase 1', function () {
    expect(TipoEdificio::count())->toBe(1);
});

test('seeds the barracks with a free default water truck type', function () {
    $tipoEdificio = TipoEdificio::first();
    $carroDeAgua = TipoVehiculo::where('especialidad', 'agua')->first();

    expect($tipoEdificio->tipo_vehiculo_inicial_id)->toBe($carroDeAgua->id);
});

test('seeds the five Bomberos vehicle specialties', function () {
    expect(TipoVehiculo::count())->toBe(5);
});

test('seeds at least six realistic Bomberos mission types with requirements', function () {
    expect(TipoMision::count())->toBeGreaterThanOrEqual(6);

    TipoMision::all()->each(
        fn (TipoMision $tipoMision) => expect($tipoMision->requisitos()->count())->toBeGreaterThan(0)
    );
});

test('seeding twice does not duplicate catalog rows', function () {
    $this->seed([TipoVehiculoSeeder::class, TipoEdificioSeeder::class, TipoMisionSeeder::class]);

    expect(TipoEdificio::count())->toBe(1)
        ->and(TipoVehiculo::count())->toBe(5)
        ->and(TipoMision::count())->toBeGreaterThanOrEqual(6);
});
