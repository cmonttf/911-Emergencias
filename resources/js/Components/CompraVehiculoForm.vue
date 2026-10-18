<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    edificio: {
        type: Object,
        required: true,
    },
    tiposVehiculo: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    edificio_id: props.edificio.id,
    tipo_vehiculo_id: props.tiposVehiculo[0]?.id ?? null,
});

const estacionamientosLibres = computed(
    () => props.edificio.tipo_edificio.estacionamientos_base - props.edificio.vehiculos.length,
);

function comprar() {
    form.post(route('vehiculos.store'), { preserveScroll: true });
}
</script>

<template>
    <div class="mt-3 border-t border-gray-100 pt-3">
        <p class="text-xs text-gray-500">
            Estacionamientos libres: {{ estacionamientosLibres }} de
            {{ edificio.tipo_edificio.estacionamientos_base }}
        </p>

        <div v-if="estacionamientosLibres > 0" class="mt-2 flex gap-2">
            <label :for="`tipo-vehiculo-${edificio.id}`" class="sr-only">
                Tipo de vehículo a comprar para {{ edificio.nombre }}
            </label>
            <select
                :id="`tipo-vehiculo-${edificio.id}`"
                v-model="form.tipo_vehiculo_id"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option v-for="tipo in tiposVehiculo" :key="tipo.id" :value="tipo.id">
                    {{ tipo.nombre }} ({{ tipo.costo.toLocaleString('es-CL') }} créditos)
                </option>
            </select>

            <button
                type="button"
                :disabled="form.processing"
                class="shrink-0 rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                @click="comprar"
            >
                Comprar
            </button>
        </div>

        <p v-if="form.errors.tipo_vehiculo_id" class="mt-1 text-xs text-red-600">
            {{ form.errors.tipo_vehiculo_id }}
        </p>
        <p v-if="form.errors.edificio_id" class="mt-1 text-xs text-red-600">
            {{ form.errors.edificio_id }}
        </p>
    </div>
</template>
