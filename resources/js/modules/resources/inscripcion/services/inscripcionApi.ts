import axios from 'axios';
import { router } from '@inertiajs/svelte';
import type { RequestPayload } from '@inertiajs/core';
import type { EstadoInscripcion, RosterItem, EstudianteDisponible } from '../types/inscripcion.types';

export async function fetchRoster(idCurso: number): Promise<RosterItem[]> {
    const res = await fetch(`/admin/inscripciones_cursos/ajax/by-curso?id_curso=${idCurso}`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error('Error al cargar el roster');
    const json = await res.json();
    return json.inscripciones ?? [];
}

export async function fetchDisponibles(idCurso: number): Promise<EstudianteDisponible[]> {
    const res = await fetch(
        `/admin/inscripciones_cursos/ajax/disponibles?id_curso=${idCurso}`,
        { headers: { Accept: 'application/json' } },
    );
    if (!res.ok) throw new Error('Error al cargar estudiantes');
    const json = await res.json();
    return json.estudiantes ?? [];
}

export async function patchEstado(
    idInscripcion: number,
    estado: EstadoInscripcion,
): Promise<{ inscripcion?: Partial<RosterItem> }> {
    const { data } = await axios.patch(
        `/admin/inscripciones_cursos/${idInscripcion}/estado`,
        { estado_inscripcion: estado },
    );
    return data;
}

export async function bulkInscribir(
    idCurso: number,
    ids: number[],
): Promise<{ created: RosterItem[]; skipped: number[]; errors?: { razon?: string; error_detail?: string }[] }> {
    const { data } = await axios.post('/admin/inscripciones_cursos/bulk', {
        id_curso: idCurso,
        id_estudiantes: ids,
    });
    return data;
}

export function deleteInscripcion(
    id: number,
    options: { onSuccess: () => void; onError: () => void },
) {
    router.delete(`/admin/inscripciones_cursos/${id}`, options);
}

// ─── Form page actions ───────────────────────────────────────────────────────

export function storeInscripcion(
    data: RequestPayload,
    options: {
        onStart?: () => void;
        onSuccess?: () => void;
        onError?: (errors: Record<string, string>) => void;
        onFinish?: () => void;
    } = {},
) {
    router.post('/admin/inscripciones_cursos', data, options);
}

export function updateInscripcionForm(
    idCurso: number,
    idEstudiante: number,
    data: RequestPayload,
    options: {
        onError?: (errors: Record<string, string>) => void;
        onFinish?: () => void;
    } = {},
) {
    router.put(`/admin/inscripciones_cursos/${idCurso},${idEstudiante}`, data, options);
}

export function destroyInscripcionForm(idCurso: number, idEstudiante: number) {
    router.delete(`/admin/inscripciones_cursos/${idCurso},${idEstudiante}`);
}

export function goToInscripcionesIndex() {
    router.get('/admin/inscripciones_cursos');
}
