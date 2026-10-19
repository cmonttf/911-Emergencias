<?php

use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;

test('an authenticated player can extend their own building parking', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 1000);
    $tipoEdificio = TipoEdificio::factory()->create(['estacionamientos_base' => 2]);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();

    $respuesta = $this->actingAs($jugador)->post(route('estacionamientos.store', $edificio));

    $respuesta->assertRedirect(route('dashboard', absolute: false));
    expect($edificio->fresh()->estacionamientos_extra)->toBe(1);
});

test('a player cannot extend another players building', function () {
    $jugador = User::factory()->create();
    $otroJugador = User::factory()->create();
    abonarCreditos($jugador, 1000);
    $edificioAjeno = Edificio::factory()->for($otroJugador, 'jugador')->create();

    $this->actingAs($jugador)->post(route('estacionamientos.store', $edificioAjeno))
        ->assertForbidden();

    expect($edificioAjeno->fresh()->estacionamientos_extra)->toBe(0);
});

test('extending without enough credits fails validation and changes nothing', function () {
    config(['juego.costo_ampliacion_estacionamiento' => 1000]);

    $jugador = User::factory()->create();
    abonarCreditos($jugador, 50);
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();

    $respuesta = $this->actingAs($jugador)->post(route('estacionamientos.store', $edificio));

    $respuesta->assertSessionHasErrors('edificio_id');
    expect($edificio->fresh()->estacionamientos_extra)->toBe(0);
});

test('guests cannot extend a building', function () {
    $edificio = Edificio::factory()->create();

    $this->post(route('estacionamientos.store', $edificio))
        ->assertRedirect(route('login'));
});
