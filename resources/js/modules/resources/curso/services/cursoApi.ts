/**
 * Servicio de API para Cursos
 *
 * Centraliza toda la lógica de llamadas HTTP relacionadas con cursos.
 * Usa los callbacks de Inertia.js (onSuccess, onError) para mantener coherencia con la arquitectura.
 */

import { router } from '@inertiajs/svelte';
import type { ComponenteFormState, TipoComponente, Docente, CursoFormData } from '../types/curso.types';

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
    router.put(`/admin/cursos/${cursoId}/componentes/${componenteId}`, data as Record<string, any>, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Elimina un componente.
 */
export function deleteComponente(cursoId: number, componenteId: number, options: ApiOptions = {}) {
    router.delete(`/admin/cursos/${cursoId}/componentes/${componenteId}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}
