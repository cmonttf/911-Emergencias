# Central de Emergencias Chile (nombre provisional)

Juego web de despacho de emergencias para un jugador, ambientado en mapas
reales de Chile. El jugador administra cuarteles de Bomberos, bases SAMU y
comisarías de Carabineros, y despacha unidades a emergencias generadas en
direcciones reales. Proyecto de aprendizaje: arquitectura por contextos de
dominio, colas, simulación en servidor y datos geoespaciales.

Esta entrega cubre **solo la Fase 1 ("Núcleo jugable")**: registro, un
cuartel de Bomberos, compra de carros, generación y despacho de misiones de
Bomberos, mapa con vehículos en movimiento y notificaciones en vivo.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 13 sobre PHP 8.5 |
| Frontend | Inertia + Vue 3 (Composition API) + Tailwind |
| Mapa | MapLibre GL (estilo MapTiler si hay API key, o el demo público si no) |
| Base de datos | **MySQL 8.4** (ver "Desviaciones del stack" abajo) |
| Colas / tiempo real | Laravel Horizon + Redis (objetivo); `database` localmente (ver abajo) |
| Tiempo real | Laravel Reverb |
| Rutas y geocodificación | OSRM y Nominatim autoalojados con el extracto de Chile de OpenStreetMap |
| Pruebas | Pest |

## Desviaciones del stack original en este entorno

El ERS original pedía PostgreSQL+PostGIS y Docker Compose para todo el
entorno. Este entorno de desarrollo específico no tuvo `sudo` interactivo
disponible para instalar Docker, así que se avanzó con ajustes documentados
aquí y en `app/Dominio/**` (ver los PHPDoc de cada decisión):

- **MySQL 8.4 en vez de PostgreSQL+PostGIS.** Las columnas de ubicación son
  `latitud`/`longitud` planas (con índice compuesto) en vez de un tipo
  espacial, con un value object `App\Dominio\Geo\ValueObjects\PuntoGeografico`
  que calcula distancias con la fórmula de Haversine y un prefiltro de caja
  delimitadora para el radio de generación de misiones. Ver
  `app/Dominio/Geo/Concerns/TienePuntoGeografico.php`.
- **Sin Docker en este entorno.** `docker-compose.yml` y `docker/php/Dockerfile`
  quedan listos para el entorno objetivo (ver más abajo), pero en este
  equipo la app corre contra un MySQL y PHP locales.
- **Redis no disponible localmente.** Colas y caché corren con el driver
  `database` de Laravel. Horizon queda instalado y configurado en el código,
  pero no se ejecuta aquí (requiere Redis real).
- **OSRM y Nominatim no están autoalojados en este entorno.** El
  `OsrmClient` y el `NominatimClient` (`app/Dominio/Geo/Clientes/`) apuntan a
  las URLs de `config/juego.php`/`.env`; todas las pruebas automatizadas los
  simulan con `Http::fake()`. Para desarrollo real, estas URLs deben apuntar
  a los servicios autoalojados (ver "Entorno con Docker" abajo).

Cuando haya Docker disponible, migrar a Postgres+PostGIS es un cambio
acotado: reemplazar las columnas `latitud`/`longitud` por `geography(Point)`
y el trait `TienePuntoGeografico` por consultas `ST_DWithin`, sin tocar el
resto de la arquitectura (acciones, jobs y eventos ya trabajan contra el
value object `PuntoGeografico`, no contra el tipo de columna).

## Instalación (entorno local sin Docker, como este)

### Requisitos

- PHP 8.5 y Composer.
- Node.js 20+ y npm.
- Un MySQL 8.4 accesible (local o remoto).

### Pasos

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Edita `.env` con los datos de tu MySQL (`DB_HOST`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD`) y crea la base de datos:

```bash
mysql -u root -p -e "CREATE DATABASE emergencias;"
```

Migra y siembra los catálogos (tipos de edificio, vehículo y misión de
Bomberos):

```bash
php artisan migrate --seed
```

Compila el frontend y levanta el servidor:

```bash
npm run build   # o `npm run dev` para recarga en caliente
php artisan serve
```

Para que las misiones se generen y venzan solas necesitas, en terminales
aparte, el scheduler y un worker de colas (con `QUEUE_CONNECTION=database`,
ya configurado por defecto):

```bash
php artisan schedule:work
php artisan queue:work
```

Y si quieres notificaciones en vivo en el navegador:

```bash
php artisan reverb:start
```

### Pruebas

```bash
php artisan test     # o vendor/bin/pest
vendor/bin/pint      # formateo de código
```

Las pruebas usan una base de datos MySQL separada (`emergencias_testing` por
defecto, ver `phpunit.xml`); créala igual que la de desarrollo. Ningún test
depende de Redis, OSRM, Nominatim ni Reverb reales: todos esos servicios se
simulan (`Http::fake()`, `Queue::fake()`, `Event::fake()`).

## Entorno con Docker (objetivo, cuando esté disponible)

`docker-compose.yml` ya define los servicios pensados para esta fase:
`app`, `horizon`, `reverb`, `scheduler`, `mysql`, `redis`, `osrm-setup` +
`osrm`, y `nominatim`. Para levantarlo:

```bash
docker compose up -d
```

El servicio `osrm-setup` descarga automáticamente el extracto de la Región
del Biobío desde Geofabrik y corre `osrm-extract`/`osrm-partition`/
`osrm-customize` la primera vez (puede tardar varios minutos). El servicio
`nominatim` importa el mismo extracto en su propia base de datos interna
(puede tardar 10-20 minutos la primera vez). Una vez arriba:

```bash
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan puntos-interes:importar "Concepción"
```

### Preparar manualmente el extracto de OpenStreetMap

Si prefieres no depender del paso automático de `osrm-setup`, puedes
descargar el extracto a mano desde Geofabrik:

```bash
curl -o biobio-latest.osm.pbf \
  https://download.geofabrik.de/south-america/chile/biobio-latest.osm.pbf
```

Esta fase usa el extracto de la **Región del Biobío**, no todo Chile, para
que la importación y el ruteo sean rápidos de iterar. Ampliar a otras
regiones implica repetir este paso con el extracto correspondiente y sumar
sus comunas a `config('juego.comunas_disponibles')`.

## Variables de entorno relevantes

Ver `.env.example` para la lista completa. Las específicas de este proyecto
están agrupadas bajo `# --- Central de Emergencias Chile ---`:

- `OSRM_BASE_URL`, `NOMINATIM_BASE_URL`: URLs de los servicios geoespaciales.
- `VITE_MAPTILER_KEY`: API key gratuita de MapTiler para el estilo del mapa
  (opcional; sin ella se usa el estilo demo público de MapLibre).
- `JUEGO_CREDITOS_INICIALES`, `JUEGO_RADIO_GENERACION_MISION_METROS`,
  `JUEGO_TOPE_MISIONES_SIMULTANEAS`, `JUEGO_FACTOR_VELOCIDAD_TIEMPO`: ver
  `config/juego.php` para el detalle de cada uno.

## Comando de importación de puntos de interés

```bash
php artisan puntos-interes:importar "Concepción"
```

Consulta el Nominatim autoalojado por categorías (vivienda, comercio,
equipamiento, industria) dentro de la comuna indicada y guarda direcciones
reales en `puntos_interes`, que `GenerarMisionesJob` usa como ubicación de
las misiones nuevas.

## Checklist de la especificación (Fase 1)

| # | Punto del alcance | Estado |
|---|---|---|
| 1 | Registro e inicio de sesión; elige comuna y recibe créditos iniciales | ✅ |
| 2 | Construir cuartel de Bomberos en el mapa, pagando créditos, con dirección por geocodificación inversa | ✅ |
| 3 | Comprar carros de bomberos limitados por estacionamientos | ✅ |
| 4 | Importación de puntos de interés desde OSM vía comando Artisan | ✅ (vía Nominatim, no parseo directo de `.osm.pbf`; ver decisión técnica) |
| 5 | Generación de misiones de Bomberos cada minuto, con cupo y radio configurables | ✅ |
| 6 | Mapa con capas de edificios, misiones y vehículos en movimiento | ✅ |
| 7 | Listado de misiones con prioridad por color y progreso | ✅ (orden por antigüedad/distancia; ver deuda técnica sobre "prioridad") |
| 8 | Detalle de misión: presentes, disponibles por tiempo de llegada, despacho manual, cancelación | ✅ |
| 9 | Resolución de misión: abona créditos y devuelve vehículos a su base | ✅ |
| 10 | Vencimiento de misión sin requisitos cubiertos | ✅ |
| 11 | Notificaciones en vivo con Reverb | ✅ (eventos probados; no se corrió contra un Reverb real en este entorno) |
| 12 | Catálogos en base de datos con seeders, ≥6 tipos de misión de Bomberos | ✅ (7 tipos) |
| — | Interfaz responsive desde 360px con navegación inferior en móvil | ✅ |
| — | Accesibilidad: foco visible, navegación por teclado, alternativa sin mapa, texto junto a color | ✅ (ver deuda técnica: no se auditó formalmente contra WCAG 2.2 AA) |
| — | Jobs idempotentes con reconciliación cada 5 minutos | ✅ |
| — | Economía como libro de transacciones, saldo nunca negativo | ✅ |
| — | PHPDoc, nombres autodescriptivos, sin valores mágicos | ✅ |
| — | `.env.example` sin secretos | ✅ |
| — | Pruebas Pest de generación, unidades disponibles, transiciones, idempotencia y economía | ✅ (105 pruebas) |

## Deuda técnica

- **PostgreSQL+PostGIS → MySQL.** Documentado arriba. La búsqueda de
  "unidades cercanas" (RNF-03, <300ms con índices espaciales) no se validó
  con el volumen de datos que supondría PostGIS; a la escala de esta fase
  (edificios de un jugador, puntos de interés de una región) el prefiltro de
  caja delimitadora sobre columnas indexadas es suficiente, pero no es un
  índice espacial real.
- **Sin Docker ni Redis en este entorno.** Horizon está instalado y
  configurado pero nunca se ejecutó contra un Redis real; tampoco se validó
  `docker compose up` de punta a punta.
- **Reverb no se probó contra un servidor real.** Los 7 eventos de dominio
  están probados a nivel de canal/payload (`broadcastOn`, `broadcastAs`,
  autorización del canal), pero nunca se verificó visualmente que el
  navegador reciba y procese las notificaciones con un Reverb corriendo.
- **"Prioridad por color" de RF-22 es aproximada.** No existe una columna de
  prioridad en `misiones`; el color de los marcadores en el mapa y del
  badge de estado refleja el `estado` de la misión, no una prioridad
  independiente basada en tiempo restante o severidad.
- **Un tipo de misión sin requisitos quedaría colgado.** Si un
  `TipoMision` no tiene ninguna fila en `requisitos_tipo_mision`,
  `Mision::requisitosCubiertos()` es verdadero por vacuidad y la misión
  nunca pasa a "en atención" (nada dispara `ResolverMisionJob`). El seeder
  siempre crea requisitos reales; falta una validación a nivel de base de
  datos o de catálogo que lo impida.
- **Sin importación real de puntos de interés en este entorno.** El comando
  `puntos-interes:importar` está probado con `Http::fake()`, pero nunca se
  corrió contra un Nominatim real con datos de Chile.
- **MapaJuego.vue es un único bundle de ~1MB.** MapLibre GL no se separa en
  un chunk aparte; vale la pena un `import()` dinámico si el tiempo de carga
  del mapa importa en producción.
- **Sin auditoría formal de accesibilidad.** Se aplicaron prácticas básicas
  (roles ARIA, foco visible, alternativa de texto a la interacción con el
  mapa, leyenda de colores con texto), pero no se corrió un verificador
  automatizado (axe, Lighthouse) ni una revisión manual completa contra
  WCAG 2.2 AA.
- **`rutas.geometria` como JSON, no como tipo espacial.** Documentado en el
  plan de la Fase 1; no se hacen consultas espaciales contra la geometría de
  una ruta, así que esto no debería requerir cambios al migrar a Postgres.
