<?php

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Helpers compartidos entre pruebas Feature, para no repetir el mismo
| abono de créditos de prueba en cada archivo.
|
*/

function abonarCreditos(User $jugador, int $monto): void
{
    (new RegistrarTransaccionAction)->ejecutar($jugador, Moneda::Creditos, $monto, MotivoTransaccion::CreditoInicial);
}

/**
 * Arma una respuesta de OSRM válida, con la anotación de velocidad por
 * tramo que `OsrmClient` necesita desde que las unidades de emergencia
 * circulan a la velocidad permitida más un extra configurable. La
 * velocidad "permitida" se calcula para que, sumándole ese extra, el
 * resultado reproduzca exactamente `$duracionSegundos` (así las pruebas
 * existentes no necesitan preocuparse por la fórmula). Cuando la duración
 * pedida es demasiado corta para esa distancia (velocidad "permitida"
 * negativa), se usa una velocidad mínima: el resultado deja de ser exacto,
 * pero sigue siendo una respuesta válida para pruebas que no verifican la
 * duración exacta.
 *
 * @param  list<array{0: float, 1: float}>  $geometria
 */
function respuestaOsrm(array $geometria, float $distanciaMetros, float $duracionSegundos): array
{
    $extraKmh = (float) config('juego.osrm.velocidad_extra_kmh');
    $velocidadEmergenciaMetrosPorSegundo = $duracionSegundos > 0 ? $distanciaMetros / $duracionSegundos : 0;
    $velocidadPermitidaMetrosPorSegundo = max(0.1, $velocidadEmergenciaMetrosPorSegundo - ($extraKmh / 3.6));

    return [
        'code' => 'Ok',
        'routes' => [[
            'geometry' => ['coordinates' => $geometria],
            'distance' => $distanciaMetros,
            'duration' => $duracionSegundos,
            'legs' => [[
                'annotation' => [
                    'distance' => [$distanciaMetros],
                    'speed' => [$velocidadPermitidaMetrosPorSegundo],
                    'duration' => [$duracionSegundos],
                ],
            ]],
        ]],
    ];
}
