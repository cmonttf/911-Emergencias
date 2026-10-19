<script setup>
import { computed } from 'vue';

const props = defineProps({
    estado: {
        type: String,
        required: true,
    },
});

const estilos = {
    disponible: { etiqueta: 'Disponible', clase: 'bg-gray-100 text-gray-800' },
    en_ruta: { etiqueta: 'En camino', clase: 'bg-blue-100 text-blue-800' },
    en_el_lugar: { etiqueta: 'En el lugar', clase: 'bg-green-100 text-green-800' },
    en_traslado: { etiqueta: 'En traslado', clase: 'bg-blue-100 text-blue-800' },
    regresando: { etiqueta: 'Regresando', clase: 'bg-amber-100 text-amber-800' },
};

const estilo = computed(() => estilos[props.estado] ?? { etiqueta: props.estado, clase: 'bg-gray-100 text-gray-800' });
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="estilo.clase"
    >
        {{ estilo.etiqueta }}
    </span>
</template>
