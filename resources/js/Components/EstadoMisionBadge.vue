<script setup>
import { computed } from 'vue';

const props = defineProps({
    estado: {
        type: String,
        required: true,
    },
});

const estilos = {
    generada: { etiqueta: 'Generada', clase: 'bg-blue-100 text-blue-800' },
    en_atencion: { etiqueta: 'En atención', clase: 'bg-amber-100 text-amber-800' },
    completada: { etiqueta: 'Completada', clase: 'bg-green-100 text-green-800' },
    fallida: { etiqueta: 'Fallida', clase: 'bg-red-100 text-red-800' },
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
