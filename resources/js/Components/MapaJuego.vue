<script setup>
import { Map as MapaLibre, Marker, NavigationControl, Popup } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

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

const contenedor = ref(null);
let mapa = null;
let marcadorPendiente = null;
const marcadoresEdificio = [];

function estiloMapa() {
    const clave = import.meta.env.VITE_MAPTILER_KEY;

    return clave
        ? `https://api.maptiler.com/maps/streets/style.json?key=${clave}`
        : 'https://demotiles.maplibre.org/style.json';
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
        dibujarEdificios();
        dibujarPuntoPendiente();
    });
});

onBeforeUnmount(() => {
    mapa?.remove();
});

watch(() => props.edificios, () => mapa?.loaded() && dibujarEdificios(), { deep: true });
watch(() => props.puntoPendiente, () => mapa?.loaded() && dibujarPuntoPendiente());
</script>

<template>
    <div
        ref="contenedor"
        class="h-full w-full"
        role="application"
        aria-label="Mapa de edificios y misiones. Usa los campos de latitud y longitud para una alternativa sin mapa."
    />
</template>
