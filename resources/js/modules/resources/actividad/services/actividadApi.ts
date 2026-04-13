import { router } from '@inertiajs/svelte';
import type { Actividad } from '@/types/actividad';

type Callback = () => void;

interface ApiOptions {
    onSuccess?: Callback;
    onError?: Callback;
}

export function createActividad(
    idCurso: number,
    data: Partial<Actividad>,
    options: ApiOptions = {},
) {
    router.post(`/docente/cursos/${idCurso}/actividades`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function updateActividad(
    idCurso: number,
    idActividad: number,
    data: Partial<Actividad>,
    options: ApiOptions = {},
) {
    router.put(`/docente/cursos/${idCurso}/actividades/${idActividad}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function deleteActividad(
    idCurso: number,
    idActividad: number,
    options: ApiOptions = {},
) {
    router.delete(`/docente/cursos/${idCurso}/actividades/${idActividad}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}
