/**
 * Constantes compartidas para módulos de administración.
 */

// ────── Opciones de paginación ──────
export const PAGINATION_OPTIONS = [10, 15, 25, 50] as const;
export const DEFAULT_PER_PAGE = 15;

// ────── Estados ──────
export const STATUS_ACTIVE = 'active';
export const STATUS_ALL = 'all';

export const STATUS_OPTIONS = {
    ACTIVE: 'active',
    ALL: 'all',
} as const;

// ────── Tipos de usuario ──────
export const USER_TYPES = {
    STUDENT: 'estudiante',
    TEACHER: 'docente',
    ADMIN: 'administrador',
} as const;

export const USER_TYPE_LABELS: Record<string, string> = {
    [USER_TYPES.STUDENT]: 'Estudiante',
    [USER_TYPES.TEACHER]: 'Docente',
    [USER_TYPES.ADMIN]: 'Administrador',
};

// ────── Carreras ──────
export const CARRERA_ATTRIBUTES = {
    SEDE: 'sede',
    JORNADA: 'jornada',
    MODALIDAD: 'modalidad',
} as const;

export const CARRERA_ATTRIBUTE_COLORS = {
    sede: 'violet',
    jornada: 'sky',
    modalidad: 'emerald',
} as const;

// ────── URLs ──────
export const ADMIN_ROUTES = {
    USUARIOS: '/admin/usuarios',
    CARRERAS: '/admin/carreras',
    FACULTADES: '/admin/facultades',
    DEPARTAMENTOS: '/admin/departamentos',
} as const;

// ────── Query params ──────
export const QUERY_PARAMS = {
    SEARCH: 'search',
    PAGE: 'page',
    PER_PAGE: 'per_page',
    STATUS: 'status',
    SORT: 'sort',
} as const;
