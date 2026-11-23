import { router } from '@inertiajs/svelte';
import { logout } from '@/routes';

/**
 * Cierra la sesión: vacía la caché de páginas de Inertia (para que nada de la
 * cuenta quede a mano del siguiente que use el navegador) y hace el POST al
 * logout, que vuelve al ingreso. La usan la puerta del pie de cada menú lateral
 * y los menús de la persona, en todos los perfiles.
 */
export function cerrarSesion(): void {
    router.flushAll();
    router.post(logout.url());
}
