<?php

use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Flota\Actions\ComprarVehiculoAction;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Exceptions\SinEstacionamientosDisponiblesException;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;

test('buys a vehicle for the building and charges its cost', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 1000);
    $tipoEdificio = TipoEdificio::factory()->create(['estacionamientos_base' => 2]);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();
    $tipoVehiculo = TipoVehiculo::factory()->create(['costo' => 600]);

    $vehiculo = app(ComprarVehiculoAction::class)->ejecutar($jugador, $edificio, $tipoVehiculo);

    expect($vehiculo->estado)->toBe(EstadoVehiculo::Disponible)
        ->and($vehiculo->edificio_id)->toBe($edificio->id)
        ->and((new Billetera($jugador))->saldo(Moneda::Creditos))->toBe(400);
});

test('throws when the building has no parking spots left', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 10_000);
    $tipoEdificio = TipoEdificio::factory()->create(['estacionamientos_base' => 1]);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();
    $tipoVehiculo = TipoVehiculo::factory()->create(['costo' => 100]);

    app(ComprarVehiculoAction::class)->ejecutar($jugador, $edificio, $tipoVehiculo);

    expect(fn () => app(ComprarVehiculoAction::class)->ejecutar($jugador, $edificio, $tipoVehiculo))
        ->toThrow(SinEstacionamientosDisponiblesException::class);

    expect(Vehiculo::count())->toBe(1);
});

test('throws when the player cannot afford the vehicle and does not create it', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 50);
    $tipoEdificio = TipoEdificio::factory()->create(['estacionamientos_base' => 2]);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();
    $tipoVehiculo = TipoVehiculo::factory()->create(['costo' => 600]);

    expect(fn () => app(ComprarVehiculoAction::class)->ejecutar($jugador, $edificio, $tipoVehiculo))
        ->toThrow(SaldoInsuficienteException::class);

    expect(Vehiculo::count())->toBe(0);
});
