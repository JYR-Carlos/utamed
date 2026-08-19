/**
 * Servicio de API para Departamentos
 *
 * Centraliza toda la lógica de llamadas HTTP relacionadas con departamentos.
 * Usa los callbacks de Inertia.js (onSuccess, onError) para mantener coherencia con la arquitectura.
 * Facilita testing, mantenimiento y reutilización en diferentes componentes.
 */

import { router } from '@inertiajs/svelte';
import type { DepartamentoFormData } from '@/types/admin.types';

/**
 * Opciones para las llamadas HTTP
 */
interface ApiOptions {
    onSuccess?: () => void;
    onError?: () => void;
}

/**
 * POST /admin/departamentos — crea un departamento asociado a una facultad.
 */
export function createDepartamento(data: DepartamentoFormData, options: ApiOptions = {}) {
    router.post('/admin/departamentos', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * PUT /admin/departamentos/{id} — actualiza un departamento existente.
 */
export function updateDepartamento(id: number, data: DepartamentoFormData, options: ApiOptions = {}) {
    router.put(`/admin/departamentos/${id}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * DELETE /admin/departamentos/{id} — discontinúa un departamento (soft
 * delete); el backend rechaza si aún tiene carreras asociadas.
 */
export function deleteDepartamento(id: number, options: ApiOptions = {}) {
    router.delete(`/admin/departamentos/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}
