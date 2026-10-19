<?php

use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Models\RequisitoTipoMision;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('the map page shows the players buildings and credit balance', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 3500);
    $tipoEdificio = TipoEdificio::factory()->create();
    Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();

    $this->actingAs($jugador)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Juego/Mapa')
            ->where('saldoCreditos', 3500)
            ->has('edificios', 1)
            ->has('misiones')
            ->has('vehiculosEnMovimiento')
            ->has('unidadesEnElLugar')
            ->has('tiposEdificio')
            ->has('tiposVehiculo')
        );
});

test('shows a unit on scene at the location of the mission it is attending', function () {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $mision = Mision::factory()->for($jugador, 'jugador')->create(['latitud' => -36.8275, 'longitud' => -73.0500]);
    $vehiculo = Vehiculo::factory()->for($edificio)->create([
        'estado' => EstadoVehiculo::EnElLugar,
        'mision_id' => $mision->id,
    ]);

    $this->actingAs($jugador)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Juego/Mapa')
            ->has('unidadesEnElLugar', 1)
            ->where('unidadesEnElLugar.0.id', $vehiculo->id)
            ->where('unidadesEnElLugar.0.latitud', '-36.8275000')
            ->where('unidadesEnElLugar.0.longitud', '-73.0500000')
        );
});

test('an active mission on the map lists the units available to dispatch it', function () {
    Http::fake([
        '*/route/v1/driving/*' => Http::response(respuestaOsrm([[-73.0498, -36.8270], [-73.0450, -36.8250]], 1000, 120), 200),
    ]);

    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::Disponible]);
    $mision = Mision::factory()->for($jugador, 'jugador')->create();
    RequisitoTipoMision::factory()->for($mision->tipoMision, 'tipoMision')->create([
        'tipo_vehiculo_id' => $vehiculo->tipo_vehiculo_id,
        'cantidad' => 1,
    ]);

    $this->actingAs($jugador)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Juego/Mapa')
            ->has('misiones', 1)
            ->has('misiones.0.unidadesDisponibles', 1)
            ->where('misiones.0.unidadesDisponibles.0.vehiculo.id', $vehiculo->id)
            ->has('misiones.0.tipo_mision.requisitos', 1)
            ->where('misiones.0.tipo_mision.requisitos.0.tipo_vehiculo.id', $vehiculo->tipo_vehiculo_id)
        );
});

test('guests are redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
