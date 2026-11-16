/**
 * Servicio de API para gestión de permisos granulares del Docente Titular.
 * 
 * Consume los endpoints AJAX de CursoPermisosController (/docente/cursos/{id}/permisos-syllabus
 * y /docente/cursos/{id}/componentes/{id}/permisos).
 */
import { csrfFetch } from '@/lib/csrf';

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
 * Carga los permisos de syllabus para un curso vía AJAX.
 */
export async function getSyllabusPermisos(
  cursoId: number,
  basePath = '/docente',
): Promise<SyllabusPermisosResponse> {
  const res = await csrfFetch(`${basePath}/cursos/${cursoId}/permisos-syllabus`, {
    headers: {
      Accept: 'application/json',
    },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err?.message ?? 'Error al cargar permisos del syllabus');
  }
  return res.json();
}

/**
 * Actualiza los permisos de syllabus para un docente vía AJAX.
 */
export async function syncSyllabusPermiso(
  cursoId: number,
  payload: { id_usuario: number; slug: string; otorgar: boolean },
  basePath = '/docente',
): Promise<void> {
  const res = await csrfFetch(`${basePath}/cursos/${cursoId}/permisos-syllabus`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err?.message ?? 'Error al actualizar permiso');
  }
}

// ─── Componente colegiado ────────────────────────────────────────────────────

/**
 * Carga los permisos de un componente colegiado vía AJAX.
 */
export async function getComponentePermisos(
  cursoId: number,
  componenteId: number,
  basePath = '/docente',
): Promise<ComponentePermisosResponse> {
  const res = await csrfFetch(`${basePath}/cursos/${cursoId}/componentes/${componenteId}/permisos`, {
    headers: {
      Accept: 'application/json',
    },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err?.message ?? 'Error al cargar permisos del componente');
  }
  return res.json();
}

/**
 * Actualiza los permisos de un componente colegiado vía AJAX.
 */
export async function syncComponentePermiso(
  cursoId: number,
  componenteId: number,
  payload: { id_usuario: number; slug: string; otorgar: boolean },
  basePath = '/docente',
): Promise<void> {
  const res = await csrfFetch(`${basePath}/cursos/${cursoId}/componentes/${componenteId}/permisos`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err?.message ?? 'Error al actualizar permiso');
  }
}
