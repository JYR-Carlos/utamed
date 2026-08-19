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

// ────── Política de contraseñas ──────
/**
 * Espejo en cliente de `Password::defaults()` (AppServiceProvider):
 * min(8) + letras + números. Es la única redacción de la política en toda
 * la interfaz: antes el alta de usuario prometía «Mín. 6 caracteres» y el
 * cambio de contraseña exigía 8 con letras y números, de modo que el
 * formulario de alta aceptaba claves que el sistema rechazaba después.
 *
 * Si cambia la regla del servidor, cambia aquí.
 */
export const PASSWORD_MIN_LENGTH = 8;
export const PASSWORD_HINT = 'Mín. 8 caracteres, con letras y números';

/** Valida en cliente lo mismo que el servidor, para avisar antes de enviar. */
export function isPasswordValid(password: string): boolean {
    return (
        password.length >= PASSWORD_MIN_LENGTH && /[a-zA-Z]/.test(password) && /\d/.test(password)
    );
}

/**
 * Genera una contraseña que cumple la política. El administrador teclea la
 * clave por cuenta de otra persona, así que conviene ofrecerle una válida
 * en lugar de dejar que invente uno de esos «123456» que el servidor
 * rechazará.
 */
export function generatePassword(length = 12): string {
    const letras = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    const numeros = '23456789';
    const todos = letras + numeros;
    const random = (set: string) => set[Math.floor(Math.random() * set.length)];

    // Garantiza al menos una letra y un número; el resto es libre.
    const chars = [random(letras), random(numeros)];
    for (let i = chars.length; i < length; i++) chars.push(random(todos));

    // Baraja para que la letra y el número no queden siempre al principio.
    for (let i = chars.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join('');
}

// ────── Carreras ──────
export const CARRERA_ATTRIBUTES = {
    SEDE: 'sede',
    JORNADA: 'jornada',
    MODALIDAD: 'modalidad',
} as const;

/**
 * Dominios cerrados de carrera. Antes eran campos de texto libre con
 * marcadores «Ej: Diurna», así que la misma jornada entraba escrita de
 * cinco formas distintas —o no entraba, que es lo que muestran las
 * columnas vacías de la tabla de departamentos.
 */
export const JORNADA_OPTIONS = ['Diurna', 'Vespertina', 'Semipresencial'] as const;
export const SEDE_OPTIONS = ['Arica', 'Iquique', 'Santiago'] as const;
export const MODALIDAD_OPTIONS = ['Presencial', 'Semipresencial', 'A distancia'] as const;

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
