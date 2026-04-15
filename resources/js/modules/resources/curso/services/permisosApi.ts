/**
 * Servicio de API para gestión de permisos granulares del Docente Titular.
 */

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

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

export async function getSyllabusPermisos(
    cursoId: number,
    basePath = '/docente',
): Promise<SyllabusPermisosResponse> {
    const res = await fetch(`${basePath}/cursos/${cursoId}/permisos-syllabus`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!res.ok) throw new Error('Error al cargar permisos del syllabus');
    return res.json();
}

export async function syncSyllabusPermiso(
    cursoId: number,
    payload: { id_usuario: number; slug: string; otorgar: boolean },
    basePath = '/docente',
): Promise<void> {
    const res = await fetch(`${basePath}/cursos/${cursoId}/permisos-syllabus`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify(payload),
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err?.message ?? 'Error al actualizar permiso');
    }
}

// ─── Componente colegiado ────────────────────────────────────────────────────

export async function getComponentePermisos(
    cursoId: number,
    componenteId: number,
    basePath = '/docente',
): Promise<ComponentePermisosResponse> {
    const res = await fetch(`${basePath}/cursos/${cursoId}/componentes/${componenteId}/permisos`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!res.ok) throw new Error('Error al cargar permisos del componente');
    return res.json();
}

export async function syncComponentePermiso(
    cursoId: number,
    componenteId: number,
    payload: { id_usuario: number; slug: string; otorgar: boolean },
    basePath = '/docente',
): Promise<void> {
    const res = await fetch(`${basePath}/cursos/${cursoId}/componentes/${componenteId}/permisos`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify(payload),
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err?.message ?? 'Error al actualizar permiso');
    }
}
