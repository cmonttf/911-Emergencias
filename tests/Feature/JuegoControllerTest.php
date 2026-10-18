<?php

use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;
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
            ->has('tiposEdificio')
            ->has('tiposVehiculo')
        );
});

test('guests are redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
