/**
 * Servicio de API para gestión de permisos granulares del Docente Titular.
 * 
 * ⚠️ IMPORTANTE: Este servicio debe ser refactorizado para usar Inertia router
 * en lugar de fetch directo. Los endpoints correspondientes en el backend
 * deben retornar Inertia::render() para GET y redirects para POST.
 * 
 * @see app/Http/Controllers/Docente/CursoPermisosController
 */
import { router } from '@inertiajs/svelte';

export interface PermisoSlug {
    id_permiso: number;
    slug: string;
    nombre: string;
}

export interface DocenteConPermisos {
    id_usuario: number;
    id_docente: number;
    nombre: string;
    es_titular?: boolean;
    /** { [id_permiso]: bool } */
    permisos: Record<number, boolean>;
}

export interface SyllabusPermisosResponse {
    docentes: DocenteConPermisos[];
    slugs_disponibles: PermisoSlug[];
    id_contexto_curso: number;
}

export interface ComponentePermisosResponse {
    docentes: DocenteConPermisos[];
    slugs_disponibles: PermisoSlug[];
    id_contexto_componente: number;
}

// ─── Syllabus ───────────────────────────────────────────────────────────────

/**
 * Carga los permisos de syllabus para un curso usando Inertia router.
 * 
 * NOTA: El endpoint debe retornar una página Inertia con los datos,
 * no JSON puro. Esta es una versión transitoria que requiere backend updates.
 * 
 * @deprecated Pending backend refactor to use Inertia::render()
 */
export async function getSyllabusPermisos(
    cursoId: number,
    basePath = '/docente',
): Promise<SyllabusPermisosResponse> {
    return new Promise((resolve, reject) => {
        router.get(`${basePath}/cursos/${cursoId}/permisos-syllabus`, {}, {
            onSuccess: (page: any) => {
                // El backend debería retornar estos datos en props
                resolve({
                    docentes: page.props?.docentes ?? [],
                    slugs_disponibles: page.props?.slugs_disponibles ?? [],
                    id_contexto_curso: page.props?.id_contexto_curso ?? 0,
                });
            },
            onError: (errors: any) => {
                reject(new Error('Error al cargar permisos del syllabus'));
            },
        });
    });
}

/**
 * Actualiza los permisos de syllabus para un docente usando Inertia router.
 * 
 * NOTA: Después de actualizar, el backend debería retornar a la página
 * de permisos con los datos actualizados, o hacer un redirect.
 */
export async function syncSyllabusPermiso(
    cursoId: number,
    payload: { id_usuario: number; slug: string; otorgar: boolean },
    basePath = '/docente',
): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(`${basePath}/cursos/${cursoId}/permisos-syllabus`, payload, {
            onSuccess: () => {
                resolve();
            },
            onError: (errors: any) => {
                reject(new Error('Error al actualizar permiso'));
            },
        });
    });
}

// ─── Componente colegiado ────────────────────────────────────────────────────

/**
 * Carga los permisos de un componente colegiado usando Inertia router.
 * 
 * @deprecated Pending backend refactor to use Inertia::render()
 */
export async function getComponentePermisos(
    cursoId: number,
    componenteId: number,
    basePath = '/docente',
): Promise<ComponentePermisosResponse> {
    return new Promise((resolve, reject) => {
        router.get(
            `${basePath}/cursos/${cursoId}/componentes/${componenteId}/permisos`,
            {},
            {
                onSuccess: (page: any) => {
                    resolve({
                        docentes: page.props?.docentes ?? [],
                        slugs_disponibles: page.props?.slugs_disponibles ?? [],
                        id_contexto_componente: page.props?.id_contexto_componente ?? 0,
                    });
                },
                onError: (errors: any) => {
                    reject(new Error('Error al cargar permisos del componente'));
                },
            },
        );
    });
}

/**
 * Actualiza los permisos de un componente colegiado usando Inertia router.
 */
export async function syncComponentePermiso(
    cursoId: number,
    componenteId: number,
    payload: { id_usuario: number; slug: string; otorgar: boolean },
    basePath = '/docente',
): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(
            `${basePath}/cursos/${cursoId}/componentes/${componenteId}/permisos`,
            payload,
            {
                onSuccess: () => {
                    resolve();
                },
                onError: (errors: any) => {
                    reject(new Error('Error al actualizar permiso'));
                },
            },
        );
    });
}
