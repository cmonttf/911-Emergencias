<script setup>
import { Map as MapaLibre, Marker, NavigationControl, Popup, setWorkerUrl } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
// MapLibre calcula la URL de su Web Worker a partir de su propia ubicación en
// tiempo de ejecución; cuando Vite empaqueta la librería (en dev, dentro del
// pre-bundle de dependencias, y en build, dentro del chunk de la página), esa
// URL relativa deja de apuntar a un archivo real y el mapa nunca carga
// ("Worker failed to load"). Resolver el worker con `?url` y fijarlo
// explícitamente funciona igual en ambos modos.
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import { caminoRestante, fraccionDeTiempo } from '@/utils/interpolarRuta';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

setWorkerUrl(maplibreWorkerUrl);

const props = defineProps({
    centro: {
        type: Object,
        default: () => ({ lat: -36.8270, lng: -73.0498 }), // Concepción
    },
    zoom: {
        type: Number,
        default: 13,
    },
    edificios: {
        type: Array,
        default: () => [],
    },
    misiones: {
        type: Array,
        default: () => [],
    },
    vehiculosEnMovimiento: {
        type: Array,
        default: () => [],
    },
    unidadesEnElLugar: {
        type: Array,
        default: () => [],
    },
    puntoPendiente: {
        type: Object,
        default: null,
    },
    editable: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['click']);

const COLOR_POR_ESTADO_MISION = {
    generada: '#f59e0b',
    en_atencion: '#f97316',
    completada: '#22c55e',
    fallida: '#ef4444',
};

const RUTA_VEHICULOS_ID = 'rutas-vehiculos';

const contenedor = ref(null);
let mapa = null;
let marcadorPendiente = null;
let cuadroAnimacion = null;
const marcadoresEdificio = [];
const marcadoresMision = [];
const marcadoresVehiculo = new Map();
const marcadoresUnidadEnElLugar = [];

function estiloMapa() {
    const clave = import.meta.env.VITE_MAPTILER_KEY;

    if (clave) {
        return `https://api.maptiler.com/maps/streets/style.json?key=${clave}`;
    }

    // El estilo demo de MapLibre (demotiles.maplibre.org) solo trae
    // polígonos de países pensados para ver el mundo completo: a un zoom de
    // ciudad como el de este juego se ve un relleno sólido sin calles ni
    // nombres. OpenFreeMap sí sirve calles y edificios reales de OSM a
    // cualquier zoom, es gratuito y no requiere cuenta ni API key.
    return 'https://tiles.openfreemap.org/styles/liberty';
}

function dibujarEdificios() {
    marcadoresEdificio.splice(0).forEach((marcador) => marcador.remove());

    props.edificios.forEach((edificio) => {
        const marcador = new Marker({ color: '#b91c1c' })
            .setLngLat([edificio.longitud, edificio.latitud])
            .setPopup(new Popup().setText(edificio.nombre))
            .addTo(mapa);

        marcadoresEdificio.push(marcador);
    });
}

function dibujarMisiones() {
    marcadoresMision.splice(0).forEach((marcador) => marcador.remove());

    props.misiones.forEach((mision) => {
        const marcador = new Marker({ color: COLOR_POR_ESTADO_MISION[mision.estado] ?? '#6b7280' })
            .setLngLat([mision.longitud, mision.latitud])
            .setPopup(new Popup().setText(`${mision.tipo_mision.nombre} — ${mision.direccion}`))
            .addTo(mapa);

        marcadoresMision.push(marcador);
    });
}

/**
 * Unidades ya en el lugar de una misión, atendiéndola: no se mueven, así
 * que se redibujan igual que los edificios o las misiones (sin
 * interpolación ni animación).
 */
function dibujarUnidadesEnElLugar() {
    marcadoresUnidadEnElLugar.splice(0).forEach((marcador) => marcador.remove());

    props.unidadesEnElLugar.forEach((unidad) => {
        const marcador = new Marker({ color: '#22c55e' })
            .setLngLat([unidad.longitud, unidad.latitud])
            .setPopup(new Popup().setText(`${unidad.tipoVehiculo} — en el lugar`))
            .addTo(mapa);

        marcadoresUnidadEnElLugar.push(marcador);
    });
}

/**
 * Camino que le falta recorrer a un vehículo en movimiento según la hora
 * (RNF-02): el primer punto es su posición actual, el resto es la ruta que
 * todavía no pasó.
 */
function caminoRestanteDe(datos) {
    const fraccion = fraccionDeTiempo(datos.salidaEn, datos.llegadaEn);

    return caminoRestante(datos.geometria, fraccion);
}

/**
 * Crea o actualiza un marcador por vehículo en movimiento. La posición se
 * recalcula en cada cuadro de animación, nunca se pide al servidor (RNF-02).
 * Se fija una posición inicial al crear el marcador (en vez de esperar al
 * primer cuadro de animación) para que nunca se vea en el origen por
 * defecto de MapLibre mientras no se haya dibujado ningún cuadro.
 */
function sincronizarMarcadoresVehiculo() {
    const idsVigentes = new Set(props.vehiculosEnMovimiento.map((vehiculo) => vehiculo.id));

    for (const [id, { marcador }] of marcadoresVehiculo) {
        if (!idsVigentes.has(id)) {
            marcador.remove();
            marcadoresVehiculo.delete(id);
        }
    }

    props.vehiculosEnMovimiento.forEach((vehiculo) => {
        if (marcadoresVehiculo.has(vehiculo.id)) {
            marcadoresVehiculo.get(vehiculo.id).datos = vehiculo;

            return;
        }

        const marcador = new Marker({ color: '#0ea5e9' })
            .setLngLat(caminoRestanteDe(vehiculo)[0])
            .setPopup(new Popup().setText(vehiculo.tipoVehiculo))
            .addTo(mapa);

        marcadoresVehiculo.set(vehiculo.id, { marcador, datos: vehiculo });
    });
}

/**
 * Si el cálculo de un vehículo fallara (p. ej. datos incompletos a mitad de
 * una actualización), no debe detener el cuadro de animación de los demás
 * ni el propio bucle: sin este try/catch, un solo error deja a todos los
 * vehículos congelados para siempre, porque nunca se volvería a agendar el
 * siguiente `requestAnimationFrame`.
 *
 * Además de mover el marcador, redibuja la ruta pendiente de cada
 * vehículo: el tramo ya recorrido se "borra" en cada cuadro porque la
 * línea solo incluye el camino que falta (caminoRestante), nunca la ruta
 * completa fija.
 */
function animar() {
    const tramosPendientes = [];

    for (const [id, { marcador, datos }] of marcadoresVehiculo) {
        try {
            const camino = caminoRestanteDe(datos);
            marcador.setLngLat(camino[0]);

            tramosPendientes.push({
                type: 'Feature',
                properties: { vehiculoId: id },
                geometry: { type: 'LineString', coordinates: camino },
            });
        } catch (error) {
            console.error(`No se pudo calcular la posición del vehículo ${id}.`, error);
        }
    }

    try {
        mapa.getSource(RUTA_VEHICULOS_ID)?.setData({ type: 'FeatureCollection', features: tramosPendientes });
    } catch (error) {
        console.error('No se pudo redibujar la ruta pendiente de los vehículos.', error);
    }

    cuadroAnimacion = requestAnimationFrame(animar);
}

function dibujarPuntoPendiente() {
    marcadorPendiente?.remove();
    marcadorPendiente = null;

    if (props.puntoPendiente) {
        marcadorPendiente = new Marker({ color: '#2563eb' })
            .setLngLat([props.puntoPendiente.lng, props.puntoPendiente.lat])
            .addTo(mapa);
    }
}

onMounted(() => {
    mapa = new MapaLibre({
        container: contenedor.value,
        style: estiloMapa(),
        center: [props.centro.lng, props.centro.lat],
        zoom: props.zoom,
    });

    mapa.addControl(new NavigationControl(), 'top-right');

    mapa.on('click', (evento) => {
        if (!props.editable) {
            return;
        }

        emit('click', { lat: evento.lngLat.lat, lng: evento.lngLat.lng });
    });

    mapa.on('load', () => {
        // Si este callback llegara a correr dos veces para el mismo mapa
        // (p. ej. un recarga en caliente de Vite durante desarrollo),
        // addSource lanzaría "Source already exists" y cortaría en seco
        // todo lo que sigue, dejando el mapa entero sin dibujar nada
        // (ni edificios ni misiones, no solo las rutas). Comprobar primero
        // lo vuelve idempotente.
        if (!mapa.getSource(RUTA_VEHICULOS_ID)) {
            mapa.addSource(RUTA_VEHICULOS_ID, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
            mapa.addLayer({
                id: RUTA_VEHICULOS_ID,
                type: 'line',
                source: RUTA_VEHICULOS_ID,
                layout: { 'line-join': 'round', 'line-cap': 'round' },
                paint: { 'line-color': '#0ea5e9', 'line-width': 3, 'line-opacity': 0.7 },
            });
        }

        dibujarEdificios();
        dibujarMisiones();
        sincronizarMarcadoresVehiculo();
        dibujarUnidadesEnElLugar();
        dibujarPuntoPendiente();
        cuadroAnimacion = requestAnimationFrame(animar);
    });
});

onBeforeUnmount(() => {
    if (cuadroAnimacion) {
        cancelAnimationFrame(cuadroAnimacion);
    }
    mapa?.remove();
});

watch(() => props.edificios, () => mapa?.loaded() && dibujarEdificios(), { deep: true });
watch(() => props.misiones, () => mapa?.loaded() && dibujarMisiones(), { deep: true });
watch(() => props.vehiculosEnMovimiento, () => mapa?.loaded() && sincronizarMarcadoresVehiculo(), { deep: true });
watch(() => props.unidadesEnElLugar, () => mapa?.loaded() && dibujarUnidadesEnElLugar(), { deep: true });
watch(() => props.puntoPendiente, () => mapa?.loaded() && dibujarPuntoPendiente());
</script>

<template>
    <div
        ref="contenedor"
        class="h-full w-full"
        role="application"
        aria-label="Mapa de edificios, misiones y vehículos en movimiento (con la ruta que les falta por recorrer) o en el lugar de una misión. Usa los campos de latitud y longitud para una alternativa sin mapa."
    />
</template>
