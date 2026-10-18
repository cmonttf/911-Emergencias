<?php

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the map page shows the players buildings and credit balance', function () {
    $jugador = User::factory()->create();
    (new RegistrarTransaccionAction)->ejecutar($jugador, Moneda::Creditos, 3500, MotivoTransaccion::CreditoInicial);
    $tipoEdificio = TipoEdificio::factory()->create();
    Edificio::factory()->for($jugador, 'jugador')->for($tipoEdificio, 'tipoEdificio')->create();

    $this->actingAs($jugador)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Juego/Mapa')
            ->where('saldoCreditos', 3500)
            ->has('edificios', 1)
            ->has('tiposEdificio')
        );
});

test('guests are redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
