<?php

use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;

test('an authenticated player can buy a vehicle for their own building', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 1000);
    $tipoEdificio = TipoEdificio::factory()->create(['estacionamientos_base' => 2]);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();
    $tipoVehiculo = TipoVehiculo::factory()->create(['costo' => 500]);

    $respuesta = $this->actingAs($jugador)->post(route('vehiculos.store'), [
        'edificio_id' => $edificio->id,
        'tipo_vehiculo_id' => $tipoVehiculo->id,
    ]);

    $respuesta->assertRedirect(route('dashboard', absolute: false));
    expect(Vehiculo::where('edificio_id', $edificio->id)->count())->toBe(1);
});

test('a player cannot buy a vehicle for someone elses building', function () {
    $jugador = User::factory()->create();
    $otroJugador = User::factory()->create();
    abonarCreditos($jugador, 1000);
    $tipoEdificio = TipoEdificio::factory()->create();
    $edificioAjeno = Edificio::factory()->for($otroJugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();
    $tipoVehiculo = TipoVehiculo::factory()->create();

    $this->actingAs($jugador)->post(route('vehiculos.store'), [
        'edificio_id' => $edificioAjeno->id,
        'tipo_vehiculo_id' => $tipoVehiculo->id,
    ])->assertForbidden();

    expect(Vehiculo::count())->toBe(0);
});

test('buying without parking spots fails validation', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 10_000);
    $tipoEdificio = TipoEdificio::factory()->create(['estacionamientos_base' => 1]);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();
    $tipoVehiculo = TipoVehiculo::factory()->create(['costo' => 100]);
    Vehiculo::factory()->for($edificio)->for($tipoVehiculo)->create();

    $this->actingAs($jugador)->post(route('vehiculos.store'), [
        'edificio_id' => $edificio->id,
        'tipo_vehiculo_id' => $tipoVehiculo->id,
    ])->assertSessionHasErrors('edificio_id');

    expect(Vehiculo::count())->toBe(1);
});
