<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Models\Despacho;
use App\Dominio\Misiones\Models\Mision;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Sin esto, el driver `sync` de pruebas encadenaría de inmediato llegada,
    // resolución y regreso del vehículo dentro de esta misma petición.
    Queue::fake();
    Http::fake([
        '*/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['geometry' => ['coordinates' => [[0, 0], [0, 0]]], 'distance' => 100, 'duration' => 100]],
        ], 200),
    ]);
});

test('dispatches a vehicle the player owns to their own mission', function () {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::Disponible]);
    $mision = Mision::factory()->for($jugador, 'jugador')->create();

    $this->actingAs($jugador)->post(route('despachos.store'), [
        'mision_id' => $mision->id,
        'vehiculo_id' => $vehiculo->id,
    ])->assertRedirect(route('dashboard', absolute: false));

    $vehiculo->refresh();
    expect($vehiculo->estado)->toBe(EstadoVehiculo::EnRuta);
});

test('cannot dispatch a vehicle belonging to another player', function () {
    $jugador = User::factory()->create();
    $otroJugador = User::factory()->create();
    $edificioAjeno = Edificio::factory()->for($otroJugador, 'jugador')->create();
    $vehiculoAjeno = Vehiculo::factory()->for($edificioAjeno)->create(['estado' => EstadoVehiculo::Disponible]);
    $mision = Mision::factory()->for($jugador, 'jugador')->create();

    $this->actingAs($jugador)->post(route('despachos.store'), [
        'mision_id' => $mision->id,
        'vehiculo_id' => $vehiculoAjeno->id,
    ])->assertForbidden();
});

test('cancels a dispatch belonging to the players own mission', function () {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $mision = Mision::factory()->for($jugador, 'jugador')->create();
    $vehiculo->update(['mision_id' => $mision->id]);
    $despacho = Despacho::factory()->for($mision)->for($vehiculo)->create([
        'salida_en' => now()->subMinute(),
        'llegada_en' => now()->addMinute(),
    ]);

    $this->actingAs($jugador)->delete(route('despachos.destroy', $despacho))
        ->assertRedirect(route('dashboard', absolute: false));

    $vehiculo->refresh();
    expect($vehiculo->estado)->toBe(EstadoVehiculo::Regresando);
});

test('cannot cancel a dispatch belonging to another players mission', function () {
    $jugador = User::factory()->create();
    $otroJugador = User::factory()->create();
    $edificio = Edificio::factory()->for($otroJugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $misionAjena = Mision::factory()->for($otroJugador, 'jugador')->create();
    $despacho = Despacho::factory()->for($misionAjena, 'mision')->for($vehiculo)->create();

    $this->actingAs($jugador)->delete(route('despachos.destroy', $despacho))
        ->assertForbidden();
});
