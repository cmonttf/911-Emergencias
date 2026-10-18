<?php

use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Models\Mision;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::fake([
        '*/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['geometry' => ['coordinates' => [[0, 0], [0, 0]]], 'distance' => 100, 'duration' => 100]],
        ], 200),
    ]);
});

test('lists only the authenticated players missions, newest first', function () {
    $jugador = User::factory()->create();
    $otroJugador = User::factory()->create();

    $antigua = Mision::factory()->for($jugador, 'jugador')->create(['created_at' => now()->subHour()]);
    $reciente = Mision::factory()->for($jugador, 'jugador')->create(['created_at' => now()]);
    Mision::factory()->for($otroJugador, 'jugador')->create();

    $this->actingAs($jugador)
        ->get(route('misiones.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Juego/Misiones/Index')
            ->has('misiones', 2)
            ->where('misiones.0.id', $reciente->id)
            ->where('misiones.1.id', $antigua->id)
        );
});

test('shows the mission detail with missing requirements and available units', function () {
    $jugador = User::factory()->create();
    $mision = Mision::factory()->for($jugador, 'jugador')->create(['estado' => EstadoMision::Generada]);

    $this->actingAs($jugador)
        ->get(route('misiones.show', $mision))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Juego/Misiones/Show')
            ->where('mision.id', $mision->id)
            ->has('requisitosFaltantes')
            ->has('unidadesDisponibles')
        );
});

test('a player cannot see another players mission detail', function () {
    $jugador = User::factory()->create();
    $otroJugador = User::factory()->create();
    $misionAjena = Mision::factory()->for($otroJugador, 'jugador')->create();

    $this->actingAs($jugador)
        ->get(route('misiones.show', $misionAjena))
        ->assertForbidden();
});
