import { router } from '@inertiajs/svelte';

export function assignAsignatura(
    planId: number,
    data: { id_asignatura: number; agno_planificado: number; semestre_planificado: number },
    options: { onSuccess?: () => void; onError?: () => void } = {},
) {
    router.post(`/admin/planes/${planId}/asignaturas`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function editAsignacion(
    planId: number,
    idAsignatura: number,
    data: { agno_planificado: number; semestre_planificado: number; tipo_ramo: number | null },
    options: { onSuccess?: () => void; onError?: () => void } = {},
) {
    router.put(`/admin/planes/${planId}/asignaturas/${idAsignatura}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function deleteAsignacion(
    planId: number,
    idAsignatura: number,
    options: { onSuccess?: () => void; onError?: () => void } = {},
) {
    router.delete(`/admin/planes/${planId}/asignaturas/${idAsignatura}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}
