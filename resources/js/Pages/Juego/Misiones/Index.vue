<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EstadoMisionBadge from '@/Components/EstadoMisionBadge.vue';
import { useCanalJugador } from '@/composables/useCanalJugador';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    misiones: {
        type: Array,
        required: true,
    },
    orden: {
        type: String,
        required: true,
    },
});

function cambiarOrden(nuevoOrden) {
    router.get(route('misiones.index'), { orden: nuevoOrden }, { preserveState: true });
}

function refrescarListado() {
    router.reload({ only: ['misiones'], preserveScroll: true });
}

useCanalJugador({
    'mision.generada': refrescarListado,
    'mision.completada': refrescarListado,
    'mision.fallida': refrescarListado,
});
</script>

<template>
    <Head title="Misiones" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Misiones
            </h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center gap-2">
                <label for="orden" class="text-sm text-gray-600">Ordenar por</label>
                <select
                    id="orden"
                    :value="orden"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @change="cambiarOrden($event.target.value)"
                >
                    <option value="antiguedad">Antigüedad</option>
                    <option value="distancia">Distancia</option>
                </select>
            </div>

            <p v-if="misiones.length === 0" class="text-sm text-gray-500">
                No hay misiones activas por ahora.
            </p>

            <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                <li v-for="mision in misiones" :key="mision.id">
                    <Link
                        :href="route('misiones.show', mision.id)"
                        class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ mision.tipo_mision.nombre }}
                            </p>
                            <p class="text-sm text-gray-500">{{ mision.direccion }}</p>
                        </div>
                        <EstadoMisionBadge :estado="mision.estado" />
                    </Link>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
