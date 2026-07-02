/**
 * actividadApi — Mutaciones de actividades del docente sobre un curso.
 *
 * Usa el router de Inertia (no fetch): cada llamada es una visita que
 * recarga props al terminar. onError recibe los errores de validación (422)
 * con el shape { campo: mensaje } que consume actividadForm.
 */
import { router } from '@inertiajs/svelte';
import type { Actividad } from '@/types/actividad';

type Callback = () => void;
type ErrorCallback = (errors: Record<string, string>) => void;

interface ApiOptions {
    onSuccess?: Callback;
    onError?: ErrorCallback;
}

/** POST /docente/cursos/{id}/actividades — crea una actividad. */
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

/** PUT /docente/cursos/{id}/actividades/{id} — actualiza una actividad. */
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

/** DELETE /docente/cursos/{id}/actividades/{id} — elimina una actividad. */
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
