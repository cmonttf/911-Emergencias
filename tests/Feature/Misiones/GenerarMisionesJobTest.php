<?php

use App\Dominio\Geo\Models\PuntoInteres;
use App\Dominio\Infraestructura\Models\Edificio;
use App\Dominio\Misiones\Enums\EstadoMision;
use App\Dominio\Misiones\Jobs\GenerarMisionesJob;
use App\Dominio\Misiones\Jobs\VencerMisionJob;
use App\Dominio\Misiones\Models\Mision;
use App\Dominio\Misiones\Models\TipoMision;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // El driver `sync` de pruebas ignora `->delay()`: sin esto,
    // VencerMisionJob se ejecutaría de inmediato y fallaría la misión recién
    // creada antes de poder comprobar su estado.
    Queue::fake();
});

test('generates a mission within the configured radius of a players building', function () {
    config(['juego.radio_generacion_mision_metros' => 3000]);

    $jugador = User::factory()->create();
    Edificio::factory()->for($jugador, 'jugador')->create(['latitud' => -36.8270, 'longitud' => -73.0498]);
    TipoMision::factory()->create();
    $puntoCercano = PuntoInteres::factory()->create(['latitud' => -36.8280, 'longitud' => -73.0500]);

    (new GenerarMisionesJob)->handle();

    expect(Mision::count())->toBe(1);
    $mision = Mision::first();
    expect($mision->jugador_id)->toBe($jugador->id)
        ->and($mision->estado)->toBe(EstadoMision::Generada)
        ->and((float) $mision->latitud)->toBe((float) $puntoCercano->latitud);

    Queue::assertPushed(VencerMisionJob::class, fn ($job) => $job->misionId === $mision->id);
});

test('does not generate a mission when no point of interest is within the radius', function () {
    config(['juego.radio_generacion_mision_metros' => 500]);

    $jugador = User::factory()->create();
    Edificio::factory()->for($jugador, 'jugador')->create(['latitud' => -36.8270, 'longitud' => -73.0498]);
    TipoMision::factory()->create();
    // A más de 10 km de distancia, fuera del radio configurado.
    PuntoInteres::factory()->create(['latitud' => -36.9200, 'longitud' => -73.1500]);

    (new GenerarMisionesJob)->handle();

    expect(Mision::count())->toBe(0);
});

test('does not generate missions for players without any building', function () {
    User::factory()->create();
    TipoMision::factory()->create();
    PuntoInteres::factory()->create(['latitud' => -36.8270, 'longitud' => -73.0498]);

    (new GenerarMisionesJob)->handle();

    expect(Mision::count())->toBe(0);
});

test('the mission cap equals the players building count plus the configured extra', function () {
    config(['juego.tope_misiones_extra_por_jugador' => 1, 'juego.radio_generacion_mision_metros' => 5000]);

    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create(['latitud' => -36.8270, 'longitud' => -73.0498]);
    Edificio::factory()->for($jugador, 'jugador')->create(['latitud' => -36.8270, 'longitud' => -73.0498]);
    TipoMision::factory()->create();
    PuntoInteres::factory()->count(5)->create(['latitud' => -36.8271, 'longitud' => -73.0499]);

    // Tope = 2 edificios + 1 extra = 3. Ya tiene 3 misiones activas.
    Mision::factory()->count(3)->for($jugador, 'jugador')->create([
        'estado' => EstadoMision::Generada,
        'latitud' => $edificio->latitud,
        'longitud' => $edificio->longitud,
    ]);

    (new GenerarMisionesJob)->handle();

    expect(Mision::count())->toBe(3);
});

test('generates a mission when below the cap for the players building count', function () {
    config(['juego.tope_misiones_extra_por_jugador' => 1, 'juego.radio_generacion_mision_metros' => 5000]);

    $jugador = User::factory()->create();
    $edificio = Edificio::factory()->for($jugador, 'jugador')->create(['latitud' => -36.8270, 'longitud' => -73.0498]);
    Edificio::factory()->for($jugador, 'jugador')->create(['latitud' => -36.8270, 'longitud' => -73.0498]);
    TipoMision::factory()->create();
    PuntoInteres::factory()->count(5)->create(['latitud' => -36.8271, 'longitud' => -73.0499]);

    // Tope = 2 edificios + 1 extra = 3. Solo tiene 2 misiones activas: hay cupo.
    Mision::factory()->count(2)->for($jugador, 'jugador')->create([
        'estado' => EstadoMision::Generada,
        'latitud' => $edificio->latitud,
        'longitud' => $edificio->longitud,
    ]);

    (new GenerarMisionesJob)->handle();

    expect(Mision::count())->toBe(3);
});
