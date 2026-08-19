/**
 * planApi — CRUD de planes curriculares y acceso a su malla.
 *
 * El prefix permite reutilizar las mismas pantallas desde admin ('/admin',
 * default histórico) o jefe de carrera ('/docente/jefe-carrera', rutas
 * acotadas a su carrera). Todas las mutaciones usan el router de Inertia;
 * fetchMalla es la excepción: pide JSON puro para el slide-over.
 */
import { router } from '@inertiajs/svelte';
import type { PlanFormData, MallaData, Plan } from '@/types/admin.types';

interface ApiOptions {
    onSuccess?: () => void;
    onError?: () => void;
}

/** POST {prefix}/planes — crea un plan. */
export function createPlan(data: PlanFormData, options: ApiOptions = {}, prefix: string = '/admin') {
    router.post(`${prefix}/planes`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** PUT {prefix}/planes/{id} — actualiza un plan. */
export function updatePlan(id: number, data: PlanFormData, options: ApiOptions = {}, prefix: string = '/admin') {
    router.put(`${prefix}/planes/${id}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** DELETE {prefix}/planes/{id} — elimina un plan (el backend rechaza si tiene malla). */
export function deletePlan(id: number, options: ApiOptions = {}, prefix: string = '/admin') {
    router.delete(`${prefix}/planes/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** Navega al editor de malla del plan. */
export function visitEditarMalla(id: number, prefix: string = '/admin') {
    router.visit(`${prefix}/planes/${id}/asignaturas`);
}

/**
 * GET {prefix}/planes/{id}/asignaturas/json — malla del plan como JSON puro
 * (sin visita Inertia), para poblar el MallaSlideOver sin recargar la página.
 */
export async function fetchMalla(plan: Plan, prefix: string = '/admin'): Promise<{ plan: Plan; malla: MallaData }> {
    const res = await fetch(`${prefix}/planes/${plan.id_plan}/asignaturas/json`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}
