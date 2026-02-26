/**
 * Formatters - Utility functions for formatting data in Svelte components
 */

/**
 * Format date to locale string
 * @param date - Date string or Date object
 * @returns Formatted date (e.g., "15/01/2024")
 */
export function formatDate(date: string | Date): string {
    try {
        const d = typeof date === 'string' ? new Date(date) : date;
        return d.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch {
        return date?.toString() || 'Fecha inválida';
    }
}

/**
 * Format datetime to locale string
 * @param date - Date string or Date object
 * @returns Formatted datetime (e.g., "15/01/2024 14:30")
 */
export function formatDateTime(date: string | Date): string {
    try {
        const d = typeof date === 'string' ? new Date(date) : date;
        return d.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
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
