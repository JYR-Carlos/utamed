/**
 * Servicio de API para Programas
 *
 * Centraliza todas las llamadas HTTP relacionadas con programas de syllabus.
 * Usa axios directamente (no Inertia router) ya que los endpoints devuelven JSON
 * puro, no redirecciones Inertia.
 *
 * basePath: ruta base dinámica según el rol del usuario, p. ej.
 *   - '/admin/cursos'
 *   - '/docente/cursos'
 *   - '/ayudante/cursos'
 */

import axios, { AxiosError } from 'axios';
import type { Programa, GuardarProgramaPayload, GenerarProgramaPayload, ProgramaFull } from '../types/programa.types';

const JSON_HEADERS = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

// ─── Loaders ─────────────────────────────────────────────────────────────────

/**
 * Carga los componentes de un curso para la sección IX del programa.
 * GET /{basePath}/{cursoId}/componentes
 */
export async function loadComponentes(cursoId: number, basePath: string) {
    const { data } = await axios.get(`${basePath}/${cursoId}/componentes`);
    return data as { componentes: any[] };
}

/**
 * Carga las actividades existentes de un curso.
 * GET /{basePath}/{cursoId}/actividades/json
 */
export async function loadActividades(cursoId: number, basePath: string) {
    const { data } = await axios.get(`${basePath}/${cursoId}/actividades/json`);
    return data as { id_actividad: number; nombre: string; fecha_limite: string }[];
}

/**
 * Carga el programa activo de un curso en formato JSON.
 * GET /{basePath}/{cursoId}/programa/json
 */
export async function loadProgramaJson(cursoId: number, basePath: string): Promise<{ programa: ProgramaFull }> {
    const { data } = await axios.get(`${basePath}/${cursoId}/programa/json`, {
        headers: JSON_HEADERS,
    });
    return data as { programa: ProgramaFull };
}

// ─── Mutations ───────────────────────────────────────────────────────────────

/**
 * Guarda los cambios de un programa existente (crea nueva versión).
 * POST /{basePath}/{cursoId}/programa  con payload de secciones.
 * @throws Error con mensaje amigable si el servidor responde con HTML (sesión expirada, etc.)
 */
export async function savePrograma(
    cursoId: number,
    payload: GuardarProgramaPayload,
    basePath: string,
): Promise<{ programa: Programa }> {
    const { data } = await axios.post(`${basePath}/${cursoId}/programa`, payload, {
        headers: JSON_HEADERS,
    });

    if (typeof data === 'string' && data.trimStart().startsWith('<!DOCTYPE')) {
        throw new Error(
            'El servidor respondió con HTML (error de validación o sesión expirada). Revisa los datos e intenta de nuevo.',
        );
    }

    return data as { programa: Programa };
}

/**
 * Genera un nuevo programa completo desde el wizard.
 * POST /{basePath}/{cursoId}/programa  con payload de wizard.
 * @throws Error con mensaje amigable
 */
export async function generatePrograma(
    cursoId: number,
    payload: GenerarProgramaPayload,
    basePath: string,
): Promise<{ programa: Programa }> {
    const { data } = await axios.post(`${basePath}/${cursoId}/programa`, payload, {
        headers: JSON_HEADERS,
    });

    if (typeof data === 'string' && data.trimStart().startsWith('<!DOCTYPE')) {
        throw new Error(
            'El servidor respondió con HTML (error de validación o sesión expirada). Revisa los datos e intenta de nuevo.',
        );
    }

    return data as { programa: Programa };
}

/**
 * Aprueba (marca como definitivo) un programa (solo admin).
 * PUT /{basePath}/{cursoId}/programa/aprobar
 */
export async function aprobarPrograma(cursoId: number, basePath: string): Promise<void> {
    await axios.put(`${basePath}/${cursoId}/programa/aprobar`);
}

// ─── Error util ──────────────────────────────────────────────────────────────

/**
 * Extrae un mensaje de error legible de una excepción de axios u otro error.
 */
export function extractErrorMessage(err: unknown, fallback = 'Error inesperado.'): string {
    if (err instanceof AxiosError) {
        return err.response?.data?.error ?? err.message;
    }
    if (err instanceof Error) {
        return err.message;
    }
    return fallback;
}
