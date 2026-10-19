<?php

use App\Dominio\Economia\Billetera;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Flota\Actions\RegresarVehiculoABaseAction;
use App\Dominio\Flota\Enums\EstadoVehiculo;
use App\Dominio\Flota\Jobs\ProcesarRegresoVehiculoJob;
use App\Dominio\Flota\Models\Vehiculo;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Actions\CancelarDespachoAction;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Jobs\VencerMisionJob;
use App\Dominio\Misiones\Models\Despacho;
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

test('fails an uncovered mission without paying credits', function () {
    $jugador = User::factory()->create();
    $tipoMision = TipoMision::factory()->create(['creditos_min' => 300, 'creditos_max' => 300]);
    RequisitoTipoMision::factory()->for($tipoMision)->create(['cantidad' => 1]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::Generada]);

    (new VencerMisionJob($mision->id))->handle(app(CancelarDespachoAction::class), app(RegresarVehiculoABaseAction::class));

    $mision->refresh();
    expect($mision->estado)->toBe(EstadoMision::Fallida)
        ->and((new Billetera($jugador))->saldo(Moneda::Creditos))->toBe(0);
});

test('recalls an en-route vehicle by cancelling its dispatch', function () {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnRuta]);
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create([
        'tipo_vehiculo_id' => $vehiculo->tipo_vehiculo_id,
        'cantidad' => 1,
    ]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::Generada]);
    $vehiculo->update(['mision_id' => $mision->id]);
    Despacho::factory()->for($mision)->for($vehiculo)->create([
        'salida_en' => now()->subMinute(),
        'llegada_en' => now()->addMinutes(5),
    ]);

    (new VencerMisionJob($mision->id))->handle(app(CancelarDespachoAction::class), app(RegresarVehiculoABaseAction::class));

    $vehiculo->refresh();
    expect($vehiculo->estado)->toBe(EstadoVehiculo::Regresando);
    Queue::assertPushed(ProcesarRegresoVehiculoJob::class, fn ($job) => $job->vehiculoId === $vehiculo->id);
});

test('recalls an on-scene vehicle directly from the mission location', function () {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnElLugar]);
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create([
        'tipo_vehiculo_id' => $vehiculo->tipo_vehiculo_id,
        'cantidad' => 2, // nunca se cubre con solo esta unidad
    ]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::Generada]);
    $vehiculo->update(['mision_id' => $mision->id]);

    (new VencerMisionJob($mision->id))->handle(app(CancelarDespachoAction::class), app(RegresarVehiculoABaseAction::class));

    $vehiculo->refresh();
    expect($vehiculo->estado)->toBe(EstadoVehiculo::Regresando);
});

test('does not expire a mission whose requirements are already covered', function () {
    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create();
    $vehiculo = Vehiculo::factory()->for($edificio)->create(['estado' => EstadoVehiculo::EnElLugar]);
    $tipoMision = TipoMision::factory()->create();
    RequisitoTipoMision::factory()->for($tipoMision)->create([
        'tipo_vehiculo_id' => $vehiculo->tipo_vehiculo_id,
        'cantidad' => 1,
    ]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::EnAtencion]);
    $vehiculo->update(['mision_id' => $mision->id]);

    (new VencerMisionJob($mision->id))->handle(app(CancelarDespachoAction::class), app(RegresarVehiculoABaseAction::class));

    $mision->refresh();
    expect($mision->estado)->toBe(EstadoMision::EnAtencion);
});

test('is idempotent when run twice', function () {
    $jugador = User::factory()->create();
    $tipoMision = TipoMision::factory()->create(['creditos_min' => 300, 'creditos_max' => 300]);
    RequisitoTipoMision::factory()->for($tipoMision)->create(['cantidad' => 1]);
    $mision = Mision::factory()->for($jugador, 'jugador')->for($tipoMision)->create(['estado' => EstadoMision::Generada]);

    $cancelarDespacho = app(CancelarDespachoAction::class);
    $regresarVehiculo = app(RegresarVehiculoABaseAction::class);

    (new VencerMisionJob($mision->id))->handle($cancelarDespacho, $regresarVehiculo);
    (new VencerMisionJob($mision->id))->handle($cancelarDespacho, $regresarVehiculo);

    expect((new Billetera($jugador))->saldo(Moneda::Creditos))->toBe(0);
});
