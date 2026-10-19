<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EstadoMisionBadge from '@/Components/EstadoMisionBadge.vue';
import EstadoVehiculoBadge from '@/Components/EstadoVehiculoBadge.vue';
import ProgresoMision from '@/Components/ProgresoMision.vue';
import { useCanalJugador } from '@/composables/useCanalJugador';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    mision: {
        type: Object,
        required: true,
    },
    requisitosFaltantes: {
        type: Array,
        required: true,
    },
    unidadesDisponibles: {
        type: Array,
        required: true,
    },
});

function refrescarSiEsEstaMision(payload) {
    if (payload.misionId === undefined || payload.misionId === props.mision.id) {
        router.reload({ preserveScroll: true });
    }
}

useCanalJugador({
    'mision.completada': refrescarSiEsEstaMision,
    'mision.fallida': refrescarSiEsEstaMision,
    'vehiculo.llego': refrescarSiEsEstaMision,
    'despacho.creado': refrescarSiEsEstaMision,
    'despacho.cancelado': refrescarSiEsEstaMision,
});

const unidadesEnCamino = computed(() =>
    props.mision.vehiculos_asignados.filter((vehiculo) => vehiculo.estado === 'en_ruta'),
);

const unidadesPresentes = computed(() =>
    props.mision.vehiculos_asignados.filter((vehiculo) => vehiculo.estado === 'en_el_lugar'),
);

function despachoDe(vehiculoId) {
    return props.mision.despachos.find((despacho) => despacho.vehiculo_id === vehiculoId);
}

const formularioDespacho = useForm({ mision_id: props.mision.id, vehiculo_id: null });

function despachar(vehiculoId) {
    formularioDespacho.vehiculo_id = vehiculoId;
    formularioDespacho.post(route('despachos.store'), { preserveScroll: true });
}

function cancelar(vehiculoId) {
    const despacho = despachoDe(vehiculoId);
    if (despacho) {
        router.delete(route('despachos.destroy', despacho.id), { preserveScroll: true });
    }
}

function formatearDuracion(segundos) {
    const minutos = Math.round(segundos / 60);
    return minutos <= 1 ? '< 1 min' : `${minutos} min`;
}
</script>

<template>
    <Head :title="mision.tipo_mision.nombre" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ mision.tipo_mision.nombre }}
                </h2>
                <EstadoMisionBadge :estado="mision.estado" />
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-4">
                <p class="text-sm text-gray-600">{{ mision.tipo_mision.descripcion }}</p>
                <p class="mt-2 text-sm font-medium text-gray-900">{{ mision.direccion }}</p>

                <ProgresoMision
                    class="mt-4"
                    :estado="mision.estado"
                    :creada-en="mision.created_at"
                    :vence-en="mision.vence_en"
                    :resuelve-en="mision.resuelve_en"
                    :actualizada-en="mision.updated_at"
                />
            </section>

            <section
                v-if="requisitosFaltantes.length > 0"
                class="rounded-lg border border-amber-200 bg-amber-50 p-4"
                aria-labelledby="requisitos-titulo"
            >
                <h3 id="requisitos-titulo" class="text-sm font-semibold text-amber-900">
                    Requisitos faltantes
                </h3>
                <ul class="mt-2 space-y-1 text-sm text-amber-800">
                    <li v-for="req in requisitosFaltantes" :key="req.tipoVehiculo.id">
                        {{ req.faltan }} × {{ req.tipoVehiculo.nombre }}
                    </li>
                </ul>
            </section>

            <section
                v-if="unidadesPresentes.length > 0"
                class="rounded-lg border border-gray-200 bg-white p-4"
                aria-labelledby="presentes-titulo"
            >
                <h3 id="presentes-titulo" class="text-sm font-semibold text-gray-900">
                    Unidades presentes
                </h3>
                <ul class="mt-2 divide-y divide-gray-100 text-sm text-gray-700">
                    <li v-for="vehiculo in unidadesPresentes" :key="vehiculo.id" class="flex items-center justify-between py-2">
                        {{ vehiculo.tipo_vehiculo.nombre }}
                        <EstadoVehiculoBadge :estado="vehiculo.estado" />
                    </li>
                </ul>
            </section>

            <section
                v-if="unidadesEnCamino.length > 0"
                class="rounded-lg border border-gray-200 bg-white p-4"
                aria-labelledby="camino-titulo"
            >
                <h3 id="camino-titulo" class="text-sm font-semibold text-gray-900">
                    Unidades en camino
                </h3>
                <ul class="mt-2 divide-y divide-gray-100 text-sm text-gray-700">
                    <li v-for="vehiculo in unidadesEnCamino" :key="vehiculo.id" class="flex items-center justify-between gap-2 py-2">
                        <span class="flex items-center gap-2">
                            {{ vehiculo.tipo_vehiculo.nombre }}
                            <EstadoVehiculoBadge :estado="vehiculo.estado" />
                        </span>
                        <button
                            type="button"
                            class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                            @click="cancelar(vehiculo.id)"
                        >
                            Cancelar
                        </button>
                    </li>
                </ul>
            </section>

            <section
                class="rounded-lg border border-gray-200 bg-white p-4"
                aria-labelledby="disponibles-titulo"
            >
                <h3 id="disponibles-titulo" class="text-sm font-semibold text-gray-900">
                    Unidades disponibles
                </h3>

                <p v-if="unidadesDisponibles.length === 0" class="mt-2 text-sm text-gray-500">
                    No tienes unidades disponibles en este momento.
                </p>

                <ul v-else class="mt-2 divide-y divide-gray-100 text-sm text-gray-700">
                    <li
                        v-for="unidad in unidadesDisponibles"
                        :key="unidad.vehiculo.id"
                        class="flex items-center justify-between py-2"
                    >
                        <span>
                            {{ unidad.vehiculo.tipo_vehiculo.nombre }}
                            <span v-if="unidad.vehiculo.estado === 'regresando'" class="text-gray-400">
                                (regresando a su cuartel, se redirige desde donde está)
                            </span>
                            <span class="text-gray-400">· llega en {{ formatearDuracion(unidad.duracionSegundos) }}</span>
                        </span>
                        <button
                            type="button"
                            :disabled="formularioDespacho.processing"
                            class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="despachar(unidad.vehiculo.id)"
                        >
                            Despachar
                        </button>
                    </li>
                </ul>

                <p v-if="formularioDespacho.errors.vehiculo_id" class="mt-2 text-sm text-red-600">
                    {{ formularioDespacho.errors.vehiculo_id }}
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
