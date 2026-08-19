/**
 * Devuelve las iniciales de un nombre usando la PRIMERA y la ÚLTIMA palabra.
 *
 * Útil para avatares de personas (p. ej. "Juan Carlos Pérez" → "JP"). Para las
 * dos PRIMERAS palabras usar `initials` de `@/utils/formatters`.
 *
 * @param fullName - Nombre completo. Vacío/undefined → ''.
 * @returns Hasta 2 iniciales en mayúscula.
 *
 * @example
 * getInitials('Juan Pérez');        // "JP"
 * getInitials('Juan Carlos Pérez'); // "JP"
 * getInitials('Ana');               // "A"
 */
export function getInitials(fullName?: string): string {
    if (!fullName) return '';

    const names = fullName.trim().split(' ');

    if (names.length === 0) return '';
    if (names.length === 1) return names[0].charAt(0).toUpperCase();

    return `${names[0].charAt(0)}${names[names.length - 1].charAt(0)}`.toUpperCase();
}

/** Hook que expone {@link getInitials} (azúcar para uso en componentes Svelte). */
export function useInitials() {
    return { getInitials };
}
