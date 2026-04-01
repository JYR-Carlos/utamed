import { router } from '@inertiajs/svelte';
import type { PlanFormData, MallaData, Plan } from '@/types/admin.types';

interface ApiOptions {
    onSuccess?: () => void;
    onError?: () => void;
}

export function createPlan(data: PlanFormData, options: ApiOptions = {}) {
    router.post('/admin/planes', data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function updatePlan(id: number, data: PlanFormData, options: ApiOptions = {}) {
    router.put(`/admin/planes/${id}`, data, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function deletePlan(id: number, options: ApiOptions = {}) {
    router.delete(`/admin/planes/${id}`, {
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

export function visitEditarMalla(id: number) {
    router.visit(`/admin/planes/${id}/asignaturas`);
}

export async function fetchMalla(plan: Plan): Promise<{ plan: Plan; malla: MallaData }> {
    const res = await fetch(`/admin/planes/${plan.id_plan}/asignaturas/json`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}
