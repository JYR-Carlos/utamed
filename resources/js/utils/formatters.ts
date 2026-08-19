/**
 * Formatters - Utility functions for formatting data in Svelte components
 */

/**
 * Formatea una fecha como texto numérico corto en es-CL (DD/MM/AAAA).
 *
 * Para strings usa {@link parseFechaSoloDia}, evitando el desfase UTC→hora local
 * de Chile (ver la nota de locale más abajo). Para mostrar fecha + hora usar
 * {@link formatFechaHora}.
 *
 * @param date - Fecha ISO/"YYYY-MM-DD..." o `Date`.
 * @returns Ej. "15/01/2024". Devuelve la entrada como texto si no es parseable.
 */
export function formatDate(date: string | Date): string {
    try {
        const d = typeof date === 'string' ? parseFechaSoloDia(date) : date;
        return d.toLocaleDateString('es-CL', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch {
        return date?.toString() || 'Fecha inválida';
    }
}

/**
 * Format user name, handling null/undefined
 * @param name - User name or object with name properties
 * @returns Formatted name
 */
export function formatUserName(name: string | { nombre?: string; nombre_completo?: string } | null | undefined): string {
    if (!name) return 'Desconocido';

    if (typeof name === 'string') {
        return name || 'Desconocido';
    }

    if (typeof name === 'object') {
        return name.nombre_completo || name.nombre || 'Desconocido';
    }

    return 'Desconocido';
}

/**
 * Truncate text to specified length
 * @param text - Text to truncate
 * @param length - Maximum length
 * @param suffix - Suffix to append if truncated (default: "...")
 * @returns Truncated text
 */
export function truncate(text: string, length: number, suffix = '...'): string {
    if (!text || text.length <= length) return text;
    return text.slice(0, length) + suffix;
}

/**
 * Format enum state to readable text
 * @param state - State value (e.g., "BASICO_COMPLETO")
 * @returns Formatted state (e.g., "Básico Completo")
 */
export function formatState(state: string): string {
    const stateMap: Record<string, string> = {
        BORRADOR: 'Borrador',
        BASICO_COMPLETO: 'Básico Completo',
        COMPLETO: 'Completo',
        APROBADO: 'Aprobado',
        PUBLICADO: 'Publicado',
        ABIERTO: 'Abierto',
        EN_REVISION: 'En Revisión',
        RECHAZADO: 'Rechazado'
    };

    return stateMap[state] || state;
}

/**
 * Format tipo_syllabus to readable text
 * @param tipo - Tipo value (e.g., "BASICO")
 * @returns Formatted tipo (e.g., "Básico (5 secciones)")
 */
export function formatTipoSyllabus(tipo: string): string {
    return tipo === 'BASICO'
        ? 'Básico (5 secciones)'
        : 'Completo (9 secciones)';
}

/**
 * Get color class for estado
 * @param estado - Estado value
 * @returns Tailwind CSS class
 */
export function getEstadoColorClass(estado: string): string {
    switch (estado) {
        case 'BORRADOR':
            return 'bg-yellow-100 text-yellow-800 border-yellow-300';
        case 'BASICO_COMPLETO':
            return 'bg-blue-100 text-blue-800 border-blue-300';
        case 'COMPLETO':
            return 'bg-purple-100 text-purple-800 border-purple-300';
        case 'APROBADO':
            return 'bg-green-100 text-green-800 border-green-300';
        case 'PUBLICADO':
            return 'bg-teal-100 text-teal-800 border-teal-300';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-300';
    }
}

/**
 * Get color class for completeness percentage
 * @param percentage - Percentage value (0-100)
 * @returns Tailwind CSS class
 */
export function getCompletenessColorClass(percentage: number): string {
    if (percentage >= 100) return 'bg-green-500';
    if (percentage >= 75) return 'bg-blue-500';
    if (percentage >= 50) return 'bg-yellow-500';
    return 'bg-orange-500';
}

/* ──────────────────────────────────────────────────────────────────────────
 * Helpers compartidos de formato (locale es-CL) — hallazgo D-04
 *
 * Estándar de locale del producto: **es-CL**. Centralizan helpers que estaban
 * re-implementados en varios archivos de `pages/docente/` con criterios
 * inconsistentes (es-CL vs es-ES, formatos distintos).
 * ────────────────────────────────────────────────────────────────────────── */

/**
 * Parsea una fecha "solo-día" como fecha LOCAL, sin conversión UTC.
 *
 * Las fechas vienen del servidor en UTC (ej. "2026-03-03T00:00:00.000000Z"),
 * pero representan fechas del calendario en Chile. `new Date(isoUtc)` las
 * interpreta en UTC y, al mostrarlas en hora local CL (UTC-3/-4), retroceden
 * un día (desfase UTC→hora local). Por eso extraemos solo la parte YYYY-MM-DD
 * y construimos la fecha con el constructor local `new Date(year, month, day)`.
 *
 * Es la versión canónica para fechas-solo-día (rescatada de
 * `Programa.svelte:parseDeadlineDate`). Las funciones `formatFechaLarga` y
 * `formatFechaCorta` se apoyan en ella.
 *
 * @param val - Fecha en formato ISO o "YYYY-MM-DD..." (se usan los primeros 10 caracteres).
 * @returns Objeto Date construido en zona horaria local.
 */
export function parseFechaSoloDia(val: string): Date {
    const [year, month, day] = val.slice(0, 10).split('-').map(Number);
    return new Date(year, month - 1, day);
}

/**
 * Formatea una fecha-solo-día como texto largo en es-CL.
 * Usa {@link parseFechaSoloDia} para evitar el desfase UTC→hora local de Chile.
 * @param val - Fecha ISO/string, o null/undefined.
 * @returns Ej. "lunes, 03 de marzo de 2026". Devuelve '' si val es null/undefined/vacío.
 */
export function formatFechaLarga(val: string | null | undefined): string {
    if (!val) return '';
    return parseFechaSoloDia(val).toLocaleDateString('es-CL', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
}

/**
 * Formatea una fecha-solo-día como texto corto en es-CL.
 * Usa {@link parseFechaSoloDia} para evitar el desfase UTC→hora local de Chile.
 * @param val - Fecha ISO/string, o null/undefined.
 * @returns Ej. "03 mar 2026". Devuelve '' si val es null/undefined/vacío.
 */
export function formatFechaCorta(val: string | null | undefined): string {
    if (!val) return '';
    return parseFechaSoloDia(val).toLocaleDateString('es-CL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

/**
 * Formatea una fecha-solo-día como texto largo en es-CL, sin el día de la semana.
 * Usa {@link parseFechaSoloDia} para evitar el desfase UTC→hora local de Chile.
 * @param val - Fecha ISO/string, o null/undefined.
 * @returns Ej. "3 de marzo de 2026". Devuelve '' si val es null/undefined/vacío.
 */
export function formatFechaTextoLargo(val: string | null | undefined): string {
    if (!val) return '';
    return parseFechaSoloDia(val).toLocaleDateString('es-CL', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

/**
 * Formatea una fecha CON hora en es-CL (fecha corta + hora:minuto).
 *
 * A diferencia de las anteriores, aquí SÍ importa la hora, por lo que se usa
 * `new Date(val)` (interpretación con zona horaria) para mostrar el instante
 * correcto en hora local.
 *
 * @param val - Fecha/instante ISO o string, o null/undefined.
 * @returns Ej. "03 mar 2026, 14:30". Devuelve '' si val es null/undefined/vacío.
 */
export function formatFechaHora(val: string | null | undefined): string {
    if (!val) return '';
    return new Date(val).toLocaleString('es-CL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Devuelve las 2 primeras iniciales de un nombre, en mayúscula.
 * Útil para avatares. (Rescatado de `CursoDetalle.svelte:initials`.)
 * @param name - Nombre completo.
 * @returns Hasta 2 iniciales en mayúscula. Devuelve '' si name es vacío/null/undefined.
 */
export function initials(name: string): string {
    if (!name) return '';
    return name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0] ?? '')
        .join('')
        .toUpperCase();
}

/**
 * Formatea un tamaño en bytes a una cadena legible (B / KB / MB).
 * (Rescatado de `Activities/Index.svelte:formatBytes`.)
 * @param bytes - Tamaño en bytes, o null.
 * @returns Ej. "512 B", "1.5 KB", "2.0 MB". Devuelve '' si bytes es null/0/undefined.
 */
export function formatBytes(bytes: number | null): string {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
