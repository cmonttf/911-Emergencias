<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Central de Emergencias Chile" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gray-900 px-6 text-center text-white"
    >
        <h1 class="text-4xl font-bold sm:text-5xl">
            Central de Emergencias Chile
        </h1>
        <p class="mt-4 max-w-xl text-lg text-gray-300">
            Administra cuarteles de Bomberos, bases SAMU y comisarías de
            Carabineros, y despacha unidades a emergencias reales en calles
            reales de Chile.
        </p>

        <nav class="mt-8 flex gap-4" aria-label="Acceso a la cuenta">
            <Link
                v-if="canLogin"
                :href="route('login')"
                class="rounded-md border border-white px-5 py-2 font-medium transition hover:bg-white hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900"
            >
                Iniciar sesión
            </Link>
            <Link
                v-if="canRegister"
                :href="route('register')"
                class="rounded-md bg-white px-5 py-2 font-medium text-gray-900 transition hover:bg-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900"
            >
                Crear cuenta
            </Link>
        </nav>
    </div>
</template>
