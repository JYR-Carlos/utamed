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
 * POST /admin/facultades — crea una facultad.
 */
export function createFacultad(data: FacultadFormData, options: ApiOptions = {}) {
    router.post('/admin/facultades', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * PUT /admin/facultades/{id} — actualiza una facultad existente.
 */
export function updateFacultad(id: number, data: FacultadFormData, options: ApiOptions = {}) {
    router.put(`/admin/facultades/${id}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * DELETE /admin/facultades/{id} — elimina una facultad (soft delete); el
 * backend rechaza si tiene departamentos asociados.
 */
export function deleteFacultad(id: number, options: ApiOptions = {}) {
    router.delete(`/admin/facultades/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * POST /admin/departamentos — crea un departamento desde la vista de
 * facultades (duplica departamentoApi.createDepartamento para no acoplar
 * módulos; mantener ambas si cambia la ruta).
 */
export function createDepartamento(data: { nombre: string; id_facultad: number }, options: ApiOptions = {}) {
    router.post('/admin/departamentos', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * DELETE /admin/departamentos/{id} — elimina un departamento (soft delete);
 * duplica departamentoApi.deleteDepartamento (misma nota que arriba).
 */
export function deleteDepartamento(id: number, options: ApiOptions = {}) {
    router.delete(`/admin/departamentos/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}
