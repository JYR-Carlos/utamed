/**
 * Servicio de API para Facultades
 *
 * Centraliza toda la lógica de llamadas HTTP relacionadas con facultades y departamentos.
 * Usa los callbacks de Inertia.js (onSuccess, onError) para mantener coherencia con la arquitectura.
 * Facilita testing, mantenimiento y reutilización en diferentes componentes.
 */

import { router } from '@inertiajs/svelte';
import type { FacultadFormData } from '@/types/admin.types';

/**
 * Opciones para las llamadas HTTP
 */
interface ApiOptions {
    onSuccess?: () => void;
    onError?: () => void;
}

/**
 * Crea una nueva facultad.
 */
export function createFacultad(data: FacultadFormData, options: ApiOptions = {}) {
    router.post('/admin/facultades', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Actualiza una facultad existente.
 */
export function updateFacultad(id: number, data: FacultadFormData, options: ApiOptions = {}) {
    router.put(`/admin/facultades/${id}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Elimina una facultad (soft delete).
 */
export function deleteFacultad(id: number, options: ApiOptions = {}) {
    router.delete(`/admin/facultades/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Crea un nuevo departamento dentro de una facultad.
 */
export function createDepartamento(data: { nombre: string; id_facultad: number }, options: ApiOptions = {}) {
    router.post('/admin/departamentos', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Elimina un departamento (soft delete).
 */
export function deleteDepartamento(id: number, options: ApiOptions = {}) {
    router.delete(`/admin/departamentos/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}
