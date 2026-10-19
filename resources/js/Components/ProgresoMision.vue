<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    estado: {
        type: String,
        required: true,
    },
    // Cuándo se generó la misión (siempre el inicio del cronómetro).
    creadaEn: {
        type: String,
        required: true,
    },
    // Hasta cuándo tiene el jugador para cubrirla antes de que falle.
    venceEn: {
        type: String,
        required: true,
    },
    // Hasta cuándo falta para resolverla, si ya está "en atención".
    resuelveEn: {
        type: String,
        default: null,
    },
    // Desde cuándo corre el tramo de resolución actual: se usa la última
    // actualización de la misión porque es exactamente cuándo se fijó o
    // reprogramó `resuelveEn` (al cubrir los requisitos o al sumarse una
    // unidad de más que adelanta el término).
    actualizadaEn: {
        type: String,
        required: true,
    },
});

const COLOR_POR_ESTADO = {
    generada: 'bg-amber-500',
    en_atencion: 'bg-orange-500',
    completada: 'bg-green-500',
    fallida: 'bg-red-500',
};

const ahora = ref(Date.now());
let intervalo = null;

onMounted(() => {
    intervalo = setInterval(() => {
        ahora.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    clearInterval(intervalo);
});

function formatearDuracion(segundos) {
    const totalSegundos = Math.max(0, Math.round(segundos));
    const minutos = Math.floor(totalSegundos / 60).toString().padStart(2, '0');
    const segs = (totalSegundos % 60).toString().padStart(2, '0');

    return `${minutos}:${segs}`;
}

// Cronómetro: tiempo transcurrido desde que se generó la emergencia, nunca
// deja de correr aunque ya esté completada o fallida.
const segundosTranscurridos = computed(() => (ahora.value - new Date(props.creadaEn).getTime()) / 1000);

// Tramo vigente para la línea de progreso: hacia el vencimiento mientras
// está "generada", hacia la resolución mientras está "en atención". Ya
// resuelta (completada o fallida) no tiene tramo: la línea queda llena.
const tramo = computed(() => {
    if (props.estado === 'generada') {
        return {
            inicio: new Date(props.creadaEn).getTime(),
            fin: new Date(props.venceEn).getTime(),
            etiqueta: 'Vence en',
        };
    }

    if (props.estado === 'en_atencion' && props.resuelveEn) {
        return {
            inicio: new Date(props.actualizadaEn).getTime(),
            fin: new Date(props.resuelveEn).getTime(),
            etiqueta: 'Se resuelve en',
        };
    }

    return null;
});

const fraccion = computed(() => {
    if (!tramo.value) {
        return 1;
    }

    const { inicio, fin } = tramo.value;

    if (fin <= inicio) {
        return 1;
    }

    return Math.min(1, Math.max(0, (ahora.value - inicio) / (fin - inicio)));
});

const segundosRestantes = computed(() => {
    if (!tramo.value) {
        return 0;
    }

    return (tramo.value.fin - ahora.value) / 1000;
});

const colorBarra = computed(() => COLOR_POR_ESTADO[props.estado] ?? 'bg-gray-400');
</script>

<template>
    <div>
        <div class="flex items-center justify-between text-xs text-gray-500">
            <span>Cronómetro: {{ formatearDuracion(segundosTranscurridos) }}</span>
            <span v-if="tramo">{{ tramo.etiqueta }} {{ formatearDuracion(segundosRestantes) }}</span>
        </div>

        <div
            class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200"
            role="progressbar"
            :aria-valuenow="Math.round(fraccion * 100)"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full"
                :class="colorBarra"
                :style="{ width: `${fraccion * 100}%` }"
            />
        </div>
    </div>
</template>
