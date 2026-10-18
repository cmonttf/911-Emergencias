import { usePage } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount } from 'vue';

/**
 * Se suscribe al canal privado del jugador autenticado (`jugador.{id}`) y
 * conecta cada evento de dominio con su manejador mientras el componente
 * esté montado.
 *
 * @param {Record<string, (payload: object) => void>} manejadores Nombre del evento (sin el punto inicial) → función a ejecutar.
 */
export function useCanalJugador(manejadores) {
    const jugadorId = usePage().props.auth.user.id;
    let canal = null;

    onMounted(() => {
        canal = window.Echo.private(`jugador.${jugadorId}`);

        for (const [evento, manejador] of Object.entries(manejadores)) {
            canal.listen(`.${evento}`, manejador);
        }
    });

    onBeforeUnmount(() => {
        window.Echo.leave(`jugador.${jugadorId}`);
    });
}
