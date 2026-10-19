<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    edificio: {
        type: Object,
        required: true,
    },
    estacionamientosMaximos: {
        type: Number,
        required: true,
    },
    costoAmpliacion: {
        type: Number,
        required: true,
    },
});

const estacionamientosTotales = computed(
    () => props.edificio.tipo_edificio.estacionamientos_base + props.edificio.estacionamientos_extra,
);

const puedeAmpliar = computed(() => estacionamientosTotales.value < props.estacionamientosMaximos);

const form = useForm({});

function ampliar() {
    form.post(route('estacionamientos.store', props.edificio.id), { preserveScroll: true });
}
</script>

<template>
    <div class="mt-2">
        <button
            v-if="puedeAmpliar"
            type="button"
            :disabled="form.processing"
            class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
            @click="ampliar"
        >
            Ampliar estacionamientos ({{ costoAmpliacion.toLocaleString('es-CL') }} créditos)
        </button>
        <p v-else class="text-xs text-gray-400">
            Estacionamientos al máximo ({{ estacionamientosMaximos }}).
        </p>

        <p v-if="form.errors.edificio_id" class="mt-1 text-xs text-red-600">
            {{ form.errors.edificio_id }}
        </p>
    </div>
</template>
