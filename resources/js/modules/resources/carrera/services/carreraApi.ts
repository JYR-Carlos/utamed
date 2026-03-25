/**
 * Servicio de API para Carreras
 *
 * Centraliza toda la lógica de llamadas HTTP relacionadas con carreras.
 * Usa los callbacks de Inertia.js (onSuccess, onError) para mantener coherencia con la arquitectura.
 */

import { router } from '@inertiajs/svelte';
import type { CarreraFormData } from '@/types/admin.types';

/**
 * Opciones para las llamadas HTTP
 */
interface ApiOptions {
    onSuccess?: () => void;
    onError?: () => void;
}

/**
 * Crea una nueva carrera.
 */
export function createCarrera(data: CarreraFormData, options: ApiOptions = {}) {
    router.post('/admin/carreras', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Actualiza una carrera existente.
 */
export function updateCarrera(id: number, data: CarreraFormData, options: ApiOptions = {}) {
    router.put(`/admin/carreras/${id}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Discontinúa una carrera (soft delete).
 */
export function discontinueCarrera(id: number, options: ApiOptions = {}) {
    router.delete(`/admin/carreras/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/**
 * Carga departamentos activos para una facultad (para cascada en formulario).
 * @param facultadId ID de la facultad
 * @param includeDeleted Si true, incluye departamentos eliminados (para modo edición)
 */
export async function loadDepartamentos(facultadId: number, includeDeleted = false) {
    if (!facultadId) {
        return [];
    }
    try {
        const response = await fetch(`/admin/facultades/${facultadId}/departamentos`);
        let departamentos = await response.json();

        if (!includeDeleted) {
            departamentos = departamentos.filter((d: any) => !d.fecha_eliminacion);
        }

        return departamentos;
    } catch (error) {
        console.error('Error cargando departamentos:', error);
        return [];
    }
}
