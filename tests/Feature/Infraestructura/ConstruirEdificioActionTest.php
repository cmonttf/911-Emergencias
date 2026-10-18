<?php

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Geo\ValueObjects\PuntoGeografico;
use App\Dominio\Infraestructura\Actions\ConstruirEdificioAction;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Infraestructura\Models\TipoEdificio;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*/reverse*' => Http::response([
            'display_name' => 'Barros Arana 123, Concepción, Chile',
            'address' => ['city' => 'Concepción'],
        ], 200),
    ]);
});

function abonarCreditos(User $jugador, int $monto): void
{
    (new RegistrarTransaccionAction)->ejecutar($jugador, Moneda::Creditos, $monto, MotivoTransaccion::CreditoInicial);
}

test('builds the building at the given point and charges its cost', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 5000);
    $tipoEdificio = TipoEdificio::factory()->create(['costo' => 2000]);

    $edificio = app(ConstruirEdificioAction::class)->ejecutar(
        $jugador,
        $tipoEdificio,
        new PuntoGeografico(-36.8270, -73.0498),
    );

    expect($edificio)->toBeInstanceOf(Edificio::class)
        ->and($edificio->direccion)->toBe('Barros Arana 123, Concepción, Chile')
        ->and($edificio->jugador_id)->toBe($jugador->id)
        ->and((new Billetera($jugador))->saldo(Moneda::Creditos))->toBe(3000);
});

test('throws when the player cannot afford the building and does not create it', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 500);
    $tipoEdificio = TipoEdificio::factory()->create(['costo' => 2000]);

    expect(fn () => app(ConstruirEdificioAction::class)->ejecutar(
        $jugador,
        $tipoEdificio,
        new PuntoGeografico(-36.8270, -73.0498),
    ))->toThrow(SaldoInsuficienteException::class);

    expect(Edificio::count())->toBe(0);
});
