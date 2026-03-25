/**
 * Servicio de API para Cursos
 *
 * Centraliza toda la lógica de llamadas HTTP relacionadas con cursos.
 * Usa los callbacks de Inertia.js (onSuccess, onError) para mantener coherencia con la arquitectura.
 */

import { router } from '@inertiajs/svelte';
import type { SeccionFormState, TipoSeccion, Docente, CursoFormData } from '../types/curso.types';

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
 * Carga tipos de sección disponibles para seleccionar.
 */
export async function loadTiposSeccion(): Promise<TipoSeccion[]> {
    try {
        const response = await fetch('/admin/tipos-seccion');
        if (!response.ok) {
            console.error('Error cargando tipos de sección:', response.statusText);
            return [];
        }
        return await response.json();
    } catch (error) {
        console.error('Error cargando tipos de sección:', error);
        return [];
    }
}

/**
 * Carga docentes disponibles para asignar a secciones.
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
// SECCIONES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Crea una nueva sección dentro de un curso.
 */
export function createSeccion(cursoId: number, data: Record<string, any>, options: ApiOptions = {}) {
    router.post(`/admin/cursos/${cursoId}/secciones`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Actualiza una sección existente.
 */
export function updateSeccion(cursoId: number, seccionId: number, data: Record<string, any>, options: ApiOptions = {}) {
    router.put(`/admin/cursos/${cursoId}/secciones/${seccionId}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Elimina una sección (soft delete).
 */
export function deleteSeccion(cursoId: number, seccionId: number, options: ApiOptions = {}) {
    router.delete(`/admin/cursos/${cursoId}/secciones/${seccionId}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}
