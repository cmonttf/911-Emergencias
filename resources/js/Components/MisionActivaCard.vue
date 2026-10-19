<script setup>
import DespacharUnidadesForm from '@/Components/DespacharUnidadesForm.vue';
import EstadoMisionBadge from '@/Components/EstadoMisionBadge.vue';
import ProgresoMision from '@/Components/ProgresoMision.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    mision: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-3">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <Link
                    :href="route('misiones.show', mision.id)"
                    class="text-sm font-medium text-gray-900 hover:underline focus:outline-none focus-visible:underline"
                >
                    {{ mision.tipo_mision.nombre }}
                </Link>
                <p class="truncate text-xs text-gray-500">{{ mision.direccion }}</p>
            </div>
            <EstadoMisionBadge :estado="mision.estado" />
        </div>

        <ProgresoMision
            class="mt-2"
            :estado="mision.estado"
            :creada-en="mision.created_at"
            :vence-en="mision.vence_en"
            :resuelve-en="mision.resuelve_en"
            :actualizada-en="mision.updated_at"
        />

        <p class="mt-2 text-xs text-gray-500">
            Requiere:
            <span v-for="(requisito, indice) in mision.tipo_mision.requisitos" :key="requisito.id">
                {{ requisito.cantidad }}× {{ requisito.tipo_vehiculo.nombre }}<span v-if="indice < mision.tipo_mision.requisitos.length - 1">, </span>
            </span>
        </p>

        <p v-if="mision.requisitosFaltantes.length > 0" class="mt-1 text-xs text-amber-700">
            Faltan:
            <span v-for="(requisito, indice) in mision.requisitosFaltantes" :key="requisito.tipoVehiculo.id">
                {{ requisito.faltan }}× {{ requisito.tipoVehiculo.nombre }}<span v-if="indice < mision.requisitosFaltantes.length - 1">, </span>
            </span>
        </p>

        <div class="mt-3 border-t border-gray-100 pt-3">
            <DespacharUnidadesForm :mision-id="mision.id" :unidades-disponibles="mision.unidadesDisponibles" />
        </div>
    </div>
</template>
