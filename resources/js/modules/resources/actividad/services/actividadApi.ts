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

/**
 * PATCH /docente/cursos/{id}/actividades/{id}/visibilidad — alterna visible/oculta.
 *
 * `preserveScroll` + `preserveState` son parte del contrato de la interacción:
 * el interruptor vive dentro de la fila de una tabla que puede tener decenas de
 * actividades y se acciona muchas veces seguidas. Sin ellos cada clic devolvía
 * al docente al principio de la página y le borraba los filtros que tenía
 * puestos. Las props sí se refrescan: `preserveState` conserva la instancia del
 * componente, no los datos.
 */
export function toggleVisibilidadActividad(
    idCurso: number,
    idActividad: number,
    options: ApiOptions = {},
) {
    router.patch(`/docente/cursos/${idCurso}/actividades/${idActividad}/visibilidad`, {}, {
        preserveScroll: true,
        preserveState: true,
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
        preserveScroll: true,
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** PATCH /docente/cursos/{id}/actividades/{id}/grupos/{grupo} — actualiza configuración de un grupo (holgura personal, nombre). */
export function updateGrupo(
    idCurso: number,
    idActividad: number,
    idGrupo: number,
    data: { nro_dias_adicionales_para_bloqueo_personal?: number; nombre_grupo?: string },
    options: ApiOptions = {},
) {
    router.patch(`/docente/cursos/${idCurso}/actividades/${idActividad}/grupos/${idGrupo}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** POST multipart /docente/cursos/{id}/actividades/{id}/enunciado — sube/reemplaza enunciado. */
export function subirEnunciadoActividad(
    idCurso: number,
    idActividad: number,
    archivo: File,
    options: ApiOptions = {},
) {
    router.post(
        `/docente/cursos/${idCurso}/actividades/${idActividad}/enunciado`,
        { archivo },
        {
            forceFormData: true,
            onSuccess: options.onSuccess,
            onError: options.onError,
        },
    );
}

// ── Copiar actividad a curso hermano ────────────────────────────────────────

export interface CursoHermano {
    id_curso: number;
    cod_curso: string;
    letra_grupo?: string | null;
    agno_real?: number;
    semestre_real?: number;
    componentes: Array<{ id_componente: number; id_tipo_componente: number; tipo: string | null }>;
    unidades: Array<{ id_unidad: number; num_unidad?: number; nombre: string }>;
}

/** GET /docente/cursos/{id}/actividades/cursos-hermanos — cursos de la misma asignatura. */
export async function fetchCursosHermanos(idCurso: number): Promise<CursoHermano[]> {
    const res = await fetch(`/docente/cursos/${idCurso}/actividades/cursos-hermanos`);
    if (!res.ok) throw new Error('No se pudieron obtener los cursos hermanos.');
    return res.json();
}

/** POST /docente/cursos/{id}/actividades/{id}/copiar — copia la actividad al curso destino. */
export function copiarActividad(
    idCurso: number,
    idActividad: number,
    data: { id_curso_destino: number; id_componente_destino: number; id_unidad_destino: number },
    options: ApiOptions = {},
) {
    router.post(`/docente/cursos/${idCurso}/actividades/${idActividad}/copiar`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}
