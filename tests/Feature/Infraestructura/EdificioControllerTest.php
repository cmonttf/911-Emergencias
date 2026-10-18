<?php

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function fakeGeocodificacionExitosa(): void
{
    Http::fake([
        '*/reverse*' => Http::response([
            'display_name' => 'Barros Arana 123, Concepción, Chile',
            'address' => ['city' => 'Concepción'],
        ], 200),
    ]);
}

test('an authenticated player can build a headquarters', function () {
    fakeGeocodificacionExitosa();

    $jugador = User::factory()->create();
    (new RegistrarTransaccionAction)->ejecutar($jugador, Moneda::Creditos, 5000, MotivoTransaccion::CreditoInicial);
    $tipoEdificio = TipoEdificio::factory()->create(['costo' => 2000]);

    $respuesta = $this->actingAs($jugador)->post(route('edificios.store'), [
        'tipo_edificio_id' => $tipoEdificio->id,
        'latitud' => -36.8270,
        'longitud' => -73.0498,
    ]);

    $respuesta->assertRedirect(route('dashboard', absolute: false));
    expect(Edificio::where('jugador_id', $jugador->id)->count())->toBe(1);
});

test('building without enough credits fails validation and builds nothing', function () {
    fakeGeocodificacionExitosa();

    $jugador = User::factory()->create();
    (new RegistrarTransaccionAction)->ejecutar($jugador, Moneda::Creditos, 100, MotivoTransaccion::CreditoInicial);
    $tipoEdificio = TipoEdificio::factory()->create(['costo' => 2000]);

    $respuesta = $this->actingAs($jugador)->post(route('edificios.store'), [
        'tipo_edificio_id' => $tipoEdificio->id,
        'latitud' => -36.8270,
        'longitud' => -73.0498,
    ]);

    $respuesta->assertSessionHasErrors('tipo_edificio_id');
    expect(Edificio::count())->toBe(0);
});

test('guests cannot build', function () {
    $tipoEdificio = TipoEdificio::factory()->create();

    $this->post(route('edificios.store'), [
        'tipo_edificio_id' => $tipoEdificio->id,
        'latitud' => -36.8270,
        'longitud' => -73.0498,
    ])->assertRedirect(route('login'));
});

test('building fails validation when the geocoding service is unavailable, not a 500', function () {
    Http::fake(['*/reverse*' => Http::response(null, 503)]);

    $jugador = User::factory()->create();
    (new RegistrarTransaccionAction)->ejecutar($jugador, Moneda::Creditos, 5000, MotivoTransaccion::CreditoInicial);
    $tipoEdificio = TipoEdificio::factory()->create(['costo' => 2000]);

    $respuesta = $this->actingAs($jugador)->post(route('edificios.store'), [
        'tipo_edificio_id' => $tipoEdificio->id,
        'latitud' => -36.8270,
        'longitud' => -73.0498,
    ]);

    $respuesta->assertSessionHasErrors('latitud');
    expect(Edificio::count())->toBe(0);
});
