<?php

namespace App\Console\Commands\PuntosInteres;

use App\Dominio\Geo\Clientes\NominatimClient;
use App\Dominio\Geo\Exceptions\ServicioGeoNoDisponibleException;
use App\Dominio\Geo\Models\PuntoInteres;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Importa direcciones reales de una comuna desde el Nominatim autoalojado
 * hacia `puntos_interes`, para que `GenerarMisionesJob` tenga ubicaciones
 * reales donde crear misiones (RF-20).
 */
#[Signature('puntos-interes:importar {comuna}')]
#[Description('Importa direcciones reales de una comuna desde Nominatim hacia puntos_interes')]
class ImportarPuntosInteresCommand extends Command
{
    /**
     * Categoría de punto de interés => término de búsqueda en Nominatim.
     * No existe una categoría "oficial" en OSM para esto: son términos que
     * en la práctica devuelven direcciones reales y variadas por comuna.
     *
     * @var array<string, string>
     */
    private const array CATEGORIAS = [
        'vivienda' => 'calle',
        'comercio' => 'supermercado',
        'equipamiento' => 'colegio',
        'industria' => 'fábrica',
        // Para que un incendio forestal (tipos_mision.categoria_punto_interes)
        // caiga en un lugar con vegetación real y no, por ejemplo, un
        // supermercado. Nominatim solo indexa lugares con nombre propio, así
        // que la cobertura depende de qué parques/áreas verdes estén
        // nombrados en OSM para cada comuna (ver alternativa con Overpass
        // API si hace falta más cobertura).
        'vegetacion' => 'parque',
    ];

    public function handle(NominatimClient $nominatim): int
    {
        $comuna = $this->argument('comuna');
        $totalImportados = 0;

        foreach (self::CATEGORIAS as $categoria => $termino) {
            try {
                $direcciones = $nominatim->buscarDirecciones("{$termino} en {$comuna}, Chile");
            } catch (ServicioGeoNoDisponibleException $excepcion) {
                $this->components->warn("No se pudo importar la categoría «{$categoria}»: {$excepcion->getMessage()}");

                continue;
            }

            foreach ($direcciones as $direccion) {
                PuntoInteres::query()->updateOrCreate(
                    [
                        'latitud' => $direccion->punto->latitud,
                        'longitud' => $direccion->punto->longitud,
                    ],
                    [
                        'categoria' => $categoria,
                        'nombre' => $direccion->direccionCompleta,
                        'direccion' => $direccion->direccionCompleta,
                        'comuna' => $direccion->comuna ?? $comuna,
                    ],
                );
                $totalImportados++;
            }

            $this->components->info("«{$categoria}»: ".count($direcciones).' direcciones importadas.');
        }

        $this->components->info("Total importado para {$comuna}: {$totalImportados} puntos de interés.");

        return self::SUCCESS;
    }
}
