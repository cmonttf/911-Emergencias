<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    misionId: {
        type: Number,
        required: true,
    },
    unidadesDisponibles: {
        type: Array,
        required: true,
    },
});

// Un formulario propio por misión: despachar una unidad de una misión no
// debe esperar a que termine el despacho de otra (RNF-02, cada despacho es
// independiente), así que el estado "processing" solo deshabilita los
// botones de esta misión mientras está en vuelo.
const formularioDespacho = useForm({ mision_id: props.misionId, vehiculo_id: null });

function despachar(vehiculoId) {
    formularioDespacho.vehiculo_id = vehiculoId;
    formularioDespacho.post(route('despachos.store'), { preserveScroll: true });
}

function formatearDuracion(segundos) {
    const minutos = Math.round(segundos / 60);

    return minutos <= 1 ? '< 1 min' : `${minutos} min`;
}
</script>

<template>
    <div>
        <p v-if="unidadesDisponibles.length === 0" class="text-sm text-gray-500">
            No tienes unidades disponibles en este momento.
        </p>

        <ul v-else class="divide-y divide-gray-100 text-sm text-gray-700">
            <li
                v-for="unidad in unidadesDisponibles"
                :key="unidad.vehiculo.id"
                class="flex items-center justify-between gap-2 py-2"
            >
                <span>
                    {{ unidad.vehiculo.tipo_vehiculo.nombre }}
                    <span v-if="unidad.vehiculo.estado === 'regresando'" class="text-gray-400">
                        (regresando, se redirige desde donde está)
                    </span>
                    <span class="text-gray-400">· llega en {{ formatearDuracion(unidad.duracionSegundos) }}</span>
                </span>
                <button
                    type="button"
                    :disabled="formularioDespacho.processing"
                    class="shrink-0 rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="despachar(unidad.vehiculo.id)"
                >
                    Despachar
                </button>
            </li>
        </ul>

        <p v-if="formularioDespacho.errors.vehiculo_id" class="mt-2 text-sm text-red-600">
            {{ formularioDespacho.errors.vehiculo_id }}
        </p>
    </div>
</template>
