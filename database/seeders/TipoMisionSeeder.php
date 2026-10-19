<?php

namespace Database\Seeders;

use App\Dominio\Flota\Enums\EspecialidadVehiculo;
use App\Dominio\Flota\Models\TipoVehiculo;
use App\Dominio\Infraestructura\Enums\Institucion;
use App\Dominio\Misiones\Models\TipoMision;
use Illuminate\Database\Seeder;

/**
 * Catálogo de tipos de misión de Bomberos para la Fase 1: siete emergencias
 * realistas para Chile, cada una con los carros que exige para considerarse
 * atendida.
 */
class TipoMisionSeeder extends Seeder
{
    public function run(): void
    {
        $tiposVehiculo = TipoVehiculo::query()
            ->where('institucion', Institucion::Bomberos)
            ->get()
            ->keyBy(fn (TipoVehiculo $tipo) => $tipo->especialidad->value);

        foreach ($this->tipos() as $datos) {
            $requisitos = $datos['requisitos'];
            unset($datos['requisitos']);

            $tipoMision = TipoMision::query()->updateOrCreate(
                ['nombre' => $datos['nombre']],
                $datos,
            );

            foreach ($requisitos as $especialidad => $cantidad) {
                $tipoMision->requisitos()->updateOrCreate(
                    ['tipo_vehiculo_id' => $tiposVehiculo[$especialidad]->id],
                    ['cantidad' => $cantidad],
                );
            }
        }
    }

    /**
     * Los créditos de cada tipo van siempre entre 250 y 500 (RF-23): el
     * rango concreto de cada uno refleja su severidad relativa; dentro de
     * ese rango, Mision::calcularRecompensaCreditos() paga más cerca del
     * máximo mientras más rápido se haya completado.
     *
     * @return list<array{
     *     nombre: string,
     *     institucion_principal: Institucion,
     *     descripcion: string,
     *     creditos_min: int,
     *     creditos_max: int,
     *     tiempo_limite_segundos: int,
     *     requisitos: array<string, int>,
     * }>
     */
    private function tipos(): array
    {
        return [
            [
                'nombre' => 'Fuego en cocina',
                'institucion_principal' => Institucion::Bomberos,
                'descripcion' => 'Principio de incendio en la cocina de una vivienda.',
                'creditos_min' => 250,
                'creditos_max' => 300,
                'tiempo_limite_segundos' => 600,
                'requisitos' => [EspecialidadVehiculo::Agua->value => 1],
            ],
            [
                'nombre' => 'Incendio estructural',
                'institucion_principal' => Institucion::Bomberos,
                'descripcion' => 'Incendio declarado en una vivienda o local comercial.',
                'creditos_min' => 300,
                'creditos_max' => 380,
                'tiempo_limite_segundos' => 900,
                'requisitos' => [EspecialidadVehiculo::Agua->value => 2, EspecialidadVehiculo::Mando->value => 1],
            ],
            [
                'nombre' => 'Incendio en edificio de altura',
                'institucion_principal' => Institucion::Bomberos,
                'descripcion' => 'Incendio en un piso alto que requiere escala y agua.',
                'creditos_min' => 350,
                'creditos_max' => 420,
                'tiempo_limite_segundos' => 900,
                'requisitos' => [EspecialidadVehiculo::Escala->value => 1, EspecialidadVehiculo::Agua->value => 1],
            ],
            [
                'nombre' => 'Incendio forestal',
                'institucion_principal' => Institucion::Bomberos,
                'descripcion' => 'Fuego en pastizal o matorral cercano a viviendas.',
                'creditos_min' => 300,
                'creditos_max' => 380,
                'tiempo_limite_segundos' => 1200,
                'requisitos' => [EspecialidadVehiculo::Agua->value => 2],
            ],
            [
                'nombre' => 'Rescate vehicular',
                'institucion_principal' => Institucion::Bomberos,
                'descripcion' => 'Accidente de tránsito con personas atrapadas.',
                'creditos_min' => 280,
                'creditos_max' => 350,
                'tiempo_limite_segundos' => 600,
                'requisitos' => [EspecialidadVehiculo::Rescate->value => 1],
            ],
            [
                'nombre' => 'Rescate en derrumbe',
                'institucion_principal' => Institucion::Bomberos,
                'descripcion' => 'Colapso parcial de una estructura con personas atrapadas.',
                'creditos_min' => 380,
                'creditos_max' => 460,
                'tiempo_limite_segundos' => 1200,
                'requisitos' => [EspecialidadVehiculo::Rescate->value => 1, EspecialidadVehiculo::Mando->value => 1],
            ],
            [
                'nombre' => 'Emergencia con materiales peligrosos',
                'institucion_principal' => Institucion::Bomberos,
                'descripcion' => 'Derrame o fuga de una sustancia peligrosa en una zona industrial.',
                'creditos_min' => 400,
                'creditos_max' => 500,
                'tiempo_limite_segundos' => 900,
                'requisitos' => [EspecialidadVehiculo::MaterialesPeligrosos->value => 1, EspecialidadVehiculo::Mando->value => 1],
            ],
        ];
    }
}
