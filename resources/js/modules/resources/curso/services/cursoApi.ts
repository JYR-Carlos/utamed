/**
 * Servicio de API para Cursos
 *
 * Centraliza toda la lógica de llamadas HTTP relacionadas con cursos.
 * Usa los callbacks de Inertia.js (onSuccess, onError) para mantener coherencia con la arquitectura.
 */

import { router } from '@inertiajs/svelte';
import type { ComponenteFormState, TipoComponente, Docente, CursoFormData, DocenteAsignadoComponente } from '../types/curso.types';

/**
 * Opciones para las llamadas HTTP
 */
interface ApiOptions {
    onSuccess?: () => void;
    onError?: () => void;
}

// ═══════════════════════════════════════════════════════════════════════════
// CURSOS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Crea un nuevo curso.
 */
export function createCurso(data: CursoFormData, options: ApiOptions = {}) {
    router.post('/admin/cursos', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Actualiza los datos de un curso existente.
 */
export function updateCurso(id: number, data: CursoFormData, options: ApiOptions = {}) {
    router.put(`/admin/cursos/${id}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Elimina un curso (soft delete).
 */
export function deleteCurso(id: number, options: ApiOptions = {}) {
    router.delete(`/admin/cursos/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// LOADERS (Cascadas y Selectors)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Carga tipos de componente disponibles para seleccionar.
 */
export async function loadTiposComponente(): Promise<TipoComponente[]> {
    try {
        const response = await fetch('/admin/tipos-componente');
        if (!response.ok) {
            console.error('Error cargando tipos de componente:', response.statusText);
            return [];
        }
        return await response.json();
    } catch (error) {
        console.error('Error cargando tipos de componente:', error);
        return [];
    }
}

/**
 * Carga docentes disponibles para asignar a componentes.
 */
export async function loadDocentes(): Promise<Docente[]> {
    try {
        const response = await fetch('/admin/docentes');
        if (!response.ok) {
            console.error('Error cargando docentes:', response.statusText);
            return [];
        }
        return await response.json();
    } catch (error) {
        console.error('Error cargando docentes:', error);
        return [];
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// COMPONENTES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Crea un nuevo componente dentro de un curso.
 */
export function createComponente(cursoId: number, data: ComponenteFormState, options: ApiOptions = {}) {
    router.post(`/admin/cursos/${cursoId}/componentes`, data as Record<string, any>, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Actualiza un componente existente.
 */
export function updateComponente(cursoId: number, componenteId: number, data: ComponenteFormState, options: ApiOptions = {}) {
    router.put(`/admin/cursos/componentes/${componenteId}`, data as Record<string, any>, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Elimina un componente.
 */
export function deleteComponente(cursoId: number, componenteId: number, options: ApiOptions = {}) {
    router.delete(`/admin/cursos/componentes/${componenteId}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// DOCENTES POR COMPONENTE (AJAX incremental, sin recarga de página)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Lee el token XSRF-TOKEN de la cookie para enviarlo como cabecera X-XSRF-TOKEN.
 */
function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Resultado exitoso de addDocenteComponente.
 */
export interface AddDocenteResult {
    message: string;
    docente_componente: DocenteAsignadoComponente;
}

/**
 * Agrega un docente a un componente.
 * Devuelve { docente_componente } si éxito, o { error: string } si falla.
 */
export async function addDocenteComponente(
    componenteId: number,
    idDocente: number,
): Promise<AddDocenteResult | { error: string }> {
    const response = await fetch(`/admin/cursos/componentes/${componenteId}/docentes`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ id_docente: idDocente }),
    });
    const data = await response.json();
    if (!response.ok) {
        return { error: data.error ?? data.message ?? 'Error al asignar docente' };
    }
    return data;
}

/**
 * Quita un docente de un componente.
 * Devuelve { message } si éxito, o { error: string } si falla (ej: único docente).
 */
export async function removeDocenteComponente(
    componenteId: number,
    idDocenteComponente: number,
): Promise<{ message: string } | { error: string }> {
    const response = await fetch(
        `/admin/cursos/componentes/${componenteId}/docentes/${idDocenteComponente}`,
        {
            method: 'DELETE',
            headers: {
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
        },
    );
    const data = await response.json();
    if (!response.ok) {
        return { error: data.error ?? data.message ?? 'Error al quitar docente' };
    }
    return data;
}

/**
 * Cambia el titular de un componente.
 */
export async function setTitularComponente(
    componenteId: number,
    idDocenteComponente: number,
): Promise<{ message: string } | { error: string }> {
    const response = await fetch(
        `/admin/cursos/componentes/${componenteId}/titular`,
        {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ id_docente_componente: idDocenteComponente }),
        },
    );
    return response.json();
}

/**
 * Cambia si un componente genera acta.
 * Devuelve warning si se activa en un componente no principal.
 */
export async function toggleGeneraActa(
    componenteId: number,
    generaActa: boolean,
): Promise<{ message: string; genera_acta: boolean; es_componente_principal: boolean; warning: string | null } | { error: string }> {
    const response = await fetch(
        `/admin/cursos/componentes/${componenteId}/genera-acta`,
        {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ genera_acta: generaActa }),
        },
    );
    return response.json();
}
