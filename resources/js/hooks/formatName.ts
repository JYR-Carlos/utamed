/**
 * Pone en mayúscula la primera letra de cada palabra de un nombre, conservando
 * el resto de cada palabra tal cual (no fuerza minúsculas en el resto).
 *
 * @param fullName - Nombre completo. Vacío/undefined → ''.
 * @returns Nombre con cada palabra capitalizada.
 *
 * @example
 * formatName('juan pérez'); // "Juan Pérez"
 */
export function formatName(fullName?: string): string {
    if (!fullName) return '';

    return fullName
        .trim()
        .split(' ')
        .map((name) => name.charAt(0).toUpperCase() + name.slice(1))
        .join(' ');
}

/** Hook que expone {@link formatName} (azúcar para uso en componentes Svelte). */
export function useFormatName() {
    return { formatName };
}