<?php

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Flota\Actions\RegresarVehiculoABaseAction;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Jobs\ProcesarRegresoVehiculoJob;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Jobs\ResolverMisionJob;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Models\RequisitoTipoMision;
use App\Dominio\Misiones\Models\TipoMision;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Http::fake([
        '*/route/v1/driving/*' => Http::response(
            respuestaOsrm([[-73.0498, -36.8270], [-73.0450, -36.8250]], 800, 90),
            200,
        ),
    ]);
});

function misionEnAtencionConUnidadPresente(): Mision
{
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnElLugar]);
    $tipoMision = TipoMision::factory()->create(['creditos' => 250]);
    RequisitoTipoMision::factory()->for($tipoMision)->create([
        'tipo_vehiculo_id' => $vehiculo->tipo_vehiculo_id,
        'cantidad' => 1,
    ]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::EnAtencion]);
    $vehiculo->update(['mision_id' => $mision->id]);

    return $mision->fresh();
}

test('completes the mission, pays its credits and sends vehicles back to base', function () {
    $mision = misionEnAtencionConUnidadPresente();
    $jugador = $mision->jugador;

    (new ResolverMisionJob($mision->id))->handle(app(RegistrarTransaccionAction::class), app(RegresarVehiculoABaseAction::class));

    $mision->refresh();
    expect($mision->estado)->toBe(EstadoMision::Completada)
        ->and((new Billetera($jugador))->saldo(Moneda::Creditos))->toBe(250);

    $vehiculo = $mision->vehiculosAsignados()->first();
    expect($vehiculo->estado)->toBe(EstadoVehiculo::Regresando)
        ->and($vehiculo->regresa_en)->not->toBeNull();

    Queue::assertPushed(ProcesarRegresoVehiculoJob::class, fn ($job) => $job->vehiculoId === $vehiculo->id);
});

test('is idempotent and never pays credits twice', function () {
    $mision = misionEnAtencionConUnidadPresente();
    $jugador = $mision->jugador;

    $registrarTransaccion = app(RegistrarTransaccionAction::class);
    $regresarVehiculo = app(RegresarVehiculoABaseAction::class);

    (new ResolverMisionJob($mision->id))->handle($registrarTransaccion, $regresarVehiculo);
    (new ResolverMisionJob($mision->id))->handle($registrarTransaccion, $regresarVehiculo);

    expect((new Billetera($jugador))->saldo(Moneda::Creditos))->toBe($mision->tipoMision->creditos);
});

test('does not complete when requirements are no longer covered', function () {
    $mision = misionEnAtencionConUnidadPresente();
    $mision->vehiculosAsignados()->first()->update(['estado' => EstadoVehiculo::Disponible, 'mision_id' => null]);

    (new ResolverMisionJob($mision->id))->handle(
        app(RegistrarTransaccionAction::class),
        app(RegresarVehiculoABaseAction::class),
    );

    $mision->refresh();
    expect($mision->estado)->toBe(EstadoMision::EnAtencion);
    expect((new Billetera($mision->jugador))->saldo(Moneda::Creditos))->toBe(0);
});
