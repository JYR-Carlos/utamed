/**
 * mallaApi — Mutaciones sobre la malla curricular de un plan (asignaciones
 * asignatura↔plan con año/semestre/tipo de ramo).
 *
 * Usa el router de Inertia: cada llamada es una visita que recarga props.
 * El prefix permite reutilizar las rutas desde admin ('/admin') o jefe de
 * carrera ('/docente/jefe-carrera').
 */
import { router } from '@inertiajs/svelte';

/** POST {prefix}/planes/{planId}/asignaturas — agrega una asignatura a la malla. */
export function assignAsignatura(
    planId: number,
    data: { id_asignatura: number; agno_planificado: number; semestre_planificado: number },
    options: { onSuccess?: () => void; onError?: () => void } = {},
    prefix: string = '/admin',
) {
    router.post(`${prefix}/planes/${planId}/asignaturas`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** PUT {prefix}/planes/{planId}/asignaturas/{id} — reposiciona una asignación (año/semestre/tipo). */
export function editAsignacion(
    planId: number,
    idAsignatura: number,
    data: { agno_planificado: number; semestre_planificado: number; tipo_ramo: number | null },
    options: { onSuccess?: () => void; onError?: () => void } = {},
    prefix: string = '/admin',
) {
    router.put(`${prefix}/planes/${planId}/asignaturas/${idAsignatura}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** DELETE {prefix}/planes/{planId}/asignaturas/{id} — quita la asignatura de la malla. */
export function deleteAsignacion(
    planId: number,
    idAsignatura: number,
    options: { onSuccess?: () => void; onError?: () => void } = {},
    prefix: string = '/admin',
) {
    router.delete(`${prefix}/planes/${planId}/asignaturas/${idAsignatura}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}
