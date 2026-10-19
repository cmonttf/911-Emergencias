<?php

use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Infraestructura\Actions\AmpliarEstacionamientosEdificioAction;
use App\Dominio\Infraestructura\Exceptions\EstacionamientosMaximosAlcanzadosException;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;

test('extends a building parking by one and charges the configured cost', function () {
    config(['juego.costo_ampliacion_estacionamiento' => 1000, 'juego.estacionamientos_maximos' => 15]);

    $jugador = User::factory()->create();
    abonarCreditos($jugador, 1000);
    $tipoEdificio = TipoEdificio::factory()->create(['estacionamientos_base' => 2]);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();

    $edificio = app(AmpliarEstacionamientosEdificioAction::class)->ejecutar($jugador, $edificio);

    expect($edificio->estacionamientos_extra)->toBe(1)
        ->and($edificio->estacionamientosTotales())->toBe(3)
        ->and((new Billetera($jugador))->saldo(Moneda::Creditos))->toBe(0);
});

test('throws when the building already reached the global maximum', function () {
    config(['juego.estacionamientos_maximos' => 3]);

    $jugador = User::factory()->create();
    abonarCreditos($jugador, 10_000);
    $tipoEdificio = TipoEdificio::factory()->create(['estacionamientos_base' => 2]);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')
        ->create(['estacionamientos_extra' => 1]);

    expect(fn () => app(AmpliarEstacionamientosEdificioAction::class)->ejecutar($jugador, $edificio))
        ->toThrow(EstacionamientosMaximosAlcanzadosException::class);

    expect($edificio->fresh()->estacionamientos_extra)->toBe(1);
});

test('throws when the player cannot afford the upgrade and does not extend it', function () {
    config(['juego.costo_ampliacion_estacionamiento' => 1000]);

    $jugador = User::factory()->create();
    abonarCreditos($jugador, 50);
    $tipoEdificio = TipoEdificio::factory()->create(['estacionamientos_base' => 2]);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();

    expect(fn () => app(AmpliarEstacionamientosEdificioAction::class)->ejecutar($jugador, $edificio))
        ->toThrow(SaldoInsuficienteException::class);

    expect($edificio->fresh()->estacionamientos_extra)->toBe(0);
});
