<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Navegación inferior visible solo en móvil (RNF-06). Cada ítem muestra un
 * ícono decorativo junto a su etiqueta de texto, por lo que el ícono se
 * marca como `aria-hidden` (RNF-07): la etiqueta visible ya cumple el rol
 * de texto alternativo.
 */
const items = [
    { label: 'Mapa', routeName: 'dashboard' },
    { label: 'Misiones', routeName: 'misiones.index' },
    { label: 'Perfil', routeName: 'profile.edit' },
];
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 flex border-t border-gray-200 bg-white sm:hidden"
        aria-label="Navegación principal"
    >
        <Link
            v-for="item in items"
            :key="item.routeName"
            :href="route(item.routeName)"
            :aria-current="route().current(item.routeName) ? 'page' : undefined"
            class="flex flex-1 flex-col items-center gap-1 py-2 text-xs font-medium text-gray-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
            :class="{ 'text-indigo-600': route().current(item.routeName) }"
        >
            <span
                class="h-2 w-2 rounded-full bg-current"
                aria-hidden="true"
            />
            {{ item.label }}
        </Link>
    </nav>
</template>
