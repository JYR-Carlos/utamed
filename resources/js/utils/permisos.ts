/**
 * Los slugs de permiso llegan del backend tal cual el nombre en BD
 * (p. ej. "actividades:dar_feedback") — no hay una etiqueta corta separada.
 * Se deriva un rótulo legible del segmento después de los ":" en vez de
 * mantener un diccionario paralelo que puede desincronizarse del backend.
 */
export function formatSlugAction(slug: string): string {
    const accion = slug.split(':').pop() ?? slug;
    const texto = accion.replace(/_/g, ' ');
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** true si el slug es uno de los 9 módulos de programa (modulo_1..modulo_9). */
export function esSlugModuloPrograma(slug: string): boolean {
    return /:modulo_\d+$/.test(slug);
}

/** Extrae el número de módulo (1-9) de un slug modulo_N, o null si no aplica. */
export function numeroModuloPrograma(slug: string): number | null {
    const match = slug.match(/:modulo_(\d+)$/);
    return match ? Number(match[1]) : null;
}
