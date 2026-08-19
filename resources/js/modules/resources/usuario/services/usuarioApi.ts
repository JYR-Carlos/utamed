/**
 * usuarioApi — Gestión de usuarios del admin (estudiante/docente/admin).
 *
 * Todas las rutas comparten /admin/usuarios y discriminan por el campo
 * tipo, porque un mismo id de usuario puede tener varios perfiles.
 * Usa el router de Inertia; onError recibe errores de validación (422).
 */
import { router } from '@inertiajs/svelte';
import type { EstudianteFormData, DocenteFormData, AdministradorFormData } from '@/types/admin.types';

type UserType = 'estudiante' | 'docente' | 'administrador';
type UserFormData = EstudianteFormData | DocenteFormData | AdministradorFormData;

interface ApiOptions {
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
}

/** Cambia la pestaña de tipo de usuario (recarga el listado completo). */
export function switchTipo(tipo: UserType) {
    router.get('/admin/usuarios', { tipo }, { preserveState: false });
}

/** POST /admin/usuarios — crea un usuario del tipo indicado. */
export function createUsuario(data: UserFormData & { tipo: UserType }, options: ApiOptions = {}) {
    router.post('/admin/usuarios', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** PUT /admin/usuarios/{id} — actualiza un usuario del tipo indicado. */
export function updateUsuario(
    id: number,
    data: UserFormData & { tipo: UserType },
    options: ApiOptions = {},
) {
    router.put(`/admin/usuarios/${id}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** DELETE /admin/usuarios/{id} — elimina el perfil del tipo indicado. */
export function deleteUsuario(id: number, tipo: UserType, options: ApiOptions = {}) {
    router.delete(`/admin/usuarios/${id}`, {
        data: { tipo },
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** POST /admin/usuarios/{id}/change-password — fija una nueva contraseña. */
export function changePassword(
    id: number,
    data: { password: string; password_confirmation: string },
    options: ApiOptions = {},
) {
    router.post(`/admin/usuarios/${id}/change-password`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** POST /admin/usuarios/{id}/toggle-active — activa/desactiva el acceso. */
export function toggleActive(id: number) {
    router.post(`/admin/usuarios/${id}/toggle-active`, {}, { preserveScroll: true });
}
