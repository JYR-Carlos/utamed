import { router } from '@inertiajs/svelte';
import type { EstudianteFormData, DocenteFormData, AdministradorFormData } from '@/types/admin.types';

type UserType = 'estudiante' | 'docente' | 'administrador';
type UserFormData = EstudianteFormData | DocenteFormData | AdministradorFormData;

interface ApiOptions {
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
}

export function switchTipo(tipo: UserType) {
    router.get('/admin/usuarios', { tipo }, { preserveState: false });
}

export function createUsuario(data: UserFormData & { tipo: UserType }, options: ApiOptions = {}) {
    router.post('/admin/usuarios', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

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

export function deleteUsuario(id: number, tipo: UserType, options: ApiOptions = {}) {
    router.delete(`/admin/usuarios/${id}`, {
        data: { tipo },
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

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

export function toggleActive(id: number) {
    router.post(`/admin/usuarios/${id}/toggle-active`, {}, { preserveScroll: true });
}
