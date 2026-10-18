<?php

use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'comuna_inicial' => config('juego.comunas_disponibles')[0],
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration grants the configured starting credits', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'comuna_inicial' => config('juego.comunas_disponibles')[0],
    ]);

    $jugador = User::where('email', 'test@example.com')->firstOrFail();

    expect((new Billetera($jugador))->saldo(Moneda::Creditos))
        ->toBe(config('juego.creditos_iniciales'));
});
