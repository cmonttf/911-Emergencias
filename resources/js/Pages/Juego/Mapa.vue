<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AmpliarEstacionamientoForm from '@/Components/AmpliarEstacionamientoForm.vue';
import CompraVehiculoForm from '@/Components/CompraVehiculoForm.vue';
import MapaJuego from '@/Components/MapaJuego.vue';
import { useCanalJugador } from '@/composables/useCanalJugador';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    edificios: {
        type: Array,
        required: true,
    },
    misiones: {
        type: Array,
        required: true,
    },
    vehiculosEnMovimiento: {
        type: Array,
        required: true,
    },
    tiposEdificio: {
        type: Array,
        required: true,
    },
    tiposVehiculo: {
        type: Array,
        required: true,
    },
    saldoCreditos: {
        type: Number,
        required: true,
    },
    estacionamientosMaximos: {
        type: Number,
        required: true,
    },
    costoAmpliacionEstacionamiento: {
        type: Number,
        required: true,
    },
});

const puntoPendiente = ref(null);

const form = useForm({
    tipo_edificio_id: props.tiposEdificio[0]?.id ?? null,
    latitud: null,
    longitud: null,
});

const tipoEdificioSeleccionado = computed(() =>
    props.tiposEdificio.find((tipo) => tipo.id === form.tipo_edificio_id),
);

const puedeConstruir = computed(
    () => puntoPendiente.value !== null && tipoEdificioSeleccionado.value && !form.processing,
);

function alHacerClicEnMapa(punto) {
    puntoPendiente.value = punto;
    form.latitud = punto.lat;
    form.longitud = punto.lng;
}

function actualizarPuntoDesdeFormulario() {
    if (form.latitud !== null && form.longitud !== null) {
        puntoPendiente.value = { lat: Number(form.latitud), lng: Number(form.longitud) };
    }
}

function construir() {
    form.post(route('edificios.store'), {
        preserveScroll: true,
        onSuccess: () => {
            puntoPendiente.value = null;
            form.reset('latitud', 'longitud');
        },
    });
}

function refrescarEstadoDelJuego() {
    router.reload({
        only: ['edificios', 'misiones', 'vehiculosEnMovimiento', 'saldoCreditos'],
        preserveScroll: true,
    });
}

useCanalJugador({
    'mision.generada': refrescarEstadoDelJuego,
    'mision.completada': refrescarEstadoDelJuego,
    'mision.fallida': refrescarEstadoDelJuego,
    'despacho.creado': refrescarEstadoDelJuego,
    'despacho.cancelado': refrescarEstadoDelJuego,
    'vehiculo.llego': refrescarEstadoDelJuego,
    'vehiculo.disponible': refrescarEstadoDelJuego,
});
</script>

<template>
    <Head title="Mapa" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Mapa
                </h2>
                <p class="text-sm font-medium text-gray-600">
                    Créditos: <span class="font-semibold text-gray-900">{{ saldoCreditos.toLocaleString('es-CL') }}</span>
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid lg:grid-cols-3 lg:px-8">
            <div class="h-[60vh] overflow-hidden rounded-lg border border-gray-200 lg:col-span-2 lg:h-[75vh]">
                <MapaJuego
                    :edificios="edificios"
                    :misiones="misiones"
                    :vehiculos-en-movimiento="vehiculosEnMovimiento"
                    :punto-pendiente="puntoPendiente"
                    editable
                    @click="alHacerClicEnMapa"
                />
            </div>

            <div class="mt-4 lg:col-span-2 lg:order-last">
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600" aria-label="Referencia de colores del mapa">
                    <li class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#b91c1c]" aria-hidden="true" /> Edificio
                    </li>
                    <li class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#f59e0b]" aria-hidden="true" /> Misión generada
                    </li>
                    <li class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#f97316]" aria-hidden="true" /> Misión en atención
                    </li>
                    <li class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#0ea5e9]" aria-hidden="true" /> Vehículo en movimiento
                    </li>
                </ul>
            </div>

            <div class="mt-6 space-y-6 lg:mt-0">
                <section
                    class="rounded-lg border border-gray-200 bg-white p-4"
                    aria-labelledby="construir-titulo"
                >
                    <h3 id="construir-titulo" class="text-base font-semibold text-gray-900">
                        Construir cuartel
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Haz clic en el mapa para elegir la ubicación, o ingresa las
                        coordenadas manualmente.
                    </p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <label for="latitud" class="block text-sm font-medium text-gray-700">
                                Latitud
                            </label>
                            <input
                                id="latitud"
                                v-model="form.latitud"
                                type="number"
                                step="0.000001"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @change="actualizarPuntoDesdeFormulario"
                            />
                        </div>
                        <div>
                            <label for="longitud" class="block text-sm font-medium text-gray-700">
                                Longitud
                            </label>
                            <input
                                id="longitud"
                                v-model="form.longitud"
                                type="number"
                                step="0.000001"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @change="actualizarPuntoDesdeFormulario"
                            />
                        </div>
                    </div>

                    <div v-if="tiposEdificio.length > 1" class="mt-4">
                        <label for="tipo_edificio" class="block text-sm font-medium text-gray-700">
                            Tipo de edificio
                        </label>
                        <select
                            id="tipo_edificio"
                            v-model="form.tipo_edificio_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="tipo in tiposEdificio" :key="tipo.id" :value="tipo.id">
                                {{ tipo.nombre }} ({{ tipo.costo.toLocaleString('es-CL') }} créditos)
                            </option>
                        </select>
                    </div>

                    <p v-if="form.errors.tipo_edificio_id" class="mt-2 text-sm text-red-600">
                        {{ form.errors.tipo_edificio_id }}
                    </p>

                    <button
                        type="button"
                        :disabled="!puedeConstruir"
                        class="mt-4 w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="construir"
                    >
                        Construir {{ tipoEdificioSeleccionado?.nombre ?? '' }}
                        <span v-if="tipoEdificioSeleccionado">
                            ({{ tipoEdificioSeleccionado.costo.toLocaleString('es-CL') }} créditos)
                        </span>
                    </button>
                </section>

                <section
                    class="rounded-lg border border-gray-200 bg-white p-4"
                    aria-labelledby="edificios-titulo"
                >
                    <h3 id="edificios-titulo" class="text-base font-semibold text-gray-900">
                        Tus edificios
                    </h3>

                    <p v-if="edificios.length === 0" class="mt-2 text-sm text-gray-500">
                        Todavía no construyes ningún edificio.
                    </p>

                    <ul v-else class="mt-2 divide-y divide-gray-100">
                        <li v-for="edificio in edificios" :key="edificio.id" class="py-3">
                            <p class="text-sm font-medium text-gray-900">{{ edificio.nombre }}</p>
                            <p class="text-sm text-gray-500">{{ edificio.direccion }}</p>
                            <p class="text-xs text-gray-400">
                                {{ edificio.vehiculos.length }} vehículo(s)
                            </p>

                            <CompraVehiculoForm :edificio="edificio" :tipos-vehiculo="tiposVehiculo" />
                            <AmpliarEstacionamientoForm
                                :edificio="edificio"
                                :estacionamientos-maximos="estacionamientosMaximos"
                                :costo-ampliacion="costoAmpliacionEstacionamiento"
                            />
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
