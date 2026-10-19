<?php

use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Exceptions\SaldoInsuficienteException;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
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

test('comes with a free default vehicle when its type has one configured', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 5000);
    $tipoVehiculo = TipoVehiculo::factory()->create(['costo' => 1000]);
    $tipoEdificio = TipoEdificio::factory()->create(['costo' => 2000, 'tipo_vehiculo_inicial_id' => $tipoVehiculo->id]);

    $edificio = app(ConstruirEdificioAction::class)->ejecutar(
        $jugador,
        $tipoEdificio,
        new PuntoGeografico(-36.8270, -73.0498),
    );

    expect($edificio->vehiculos()->count())->toBe(1);
    $vehiculo = $edificio->vehiculos()->first();
    expect($vehiculo->tipo_vehiculo_id)->toBe($tipoVehiculo->id)
        ->and($vehiculo->estado)->toBe(EstadoVehiculo::Disponible)
        // Solo se cobró el costo del edificio: el vehículo inicial es gratis.
        ->and((new Billetera($jugador))->saldo(Moneda::Creditos))->toBe(3000);
});

test('does not create any vehicle when its type has no default one configured', function () {
    $jugador = User::factory()->create();
    abonarCreditos($jugador, 5000);
    $tipoEdificio = TipoEdificio::factory()->create(['costo' => 2000, 'tipo_vehiculo_inicial_id' => null]);

    $edificio = app(ConstruirEdificioAction::class)->ejecutar(
        $jugador,
        $tipoEdificio,
        new PuntoGeografico(-36.8270, -73.0498),
    );

    expect($edificio->vehiculos()->count())->toBe(0);
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
