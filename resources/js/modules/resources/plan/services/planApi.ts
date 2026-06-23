import { router } from '@inertiajs/svelte';
import type { PlanFormData, MallaData, Plan } from '@/types/admin.types';

interface ApiOptions {
    onSuccess?: () => void;
    onError?: () => void;
}

/**
 * Prefijo de rutas configurable. Por defecto '/admin' (comportamiento histórico).
 * El Jefe de Carrera lo invoca con '/docente/jefe-carrera' para operar sobre sus
 * propias rutas acotadas a su carrera, reutilizando las mismas pantallas.
 */
export function createPlan(data: PlanFormData, options: ApiOptions = {}, prefix: string = '/admin') {
    router.post(`${prefix}/planes`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function updatePlan(id: number, data: PlanFormData, options: ApiOptions = {}, prefix: string = '/admin') {
    router.put(`${prefix}/planes/${id}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function deletePlan(id: number, options: ApiOptions = {}, prefix: string = '/admin') {
    router.delete(`${prefix}/planes/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function visitEditarMalla(id: number, prefix: string = '/admin') {
    router.visit(`${prefix}/planes/${id}/asignaturas`);
}

export async function fetchMalla(plan: Plan, prefix: string = '/admin'): Promise<{ plan: Plan; malla: MallaData }> {
    const res = await fetch(`${prefix}/planes/${plan.id_plan}/asignaturas/json`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}
