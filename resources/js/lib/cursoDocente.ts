/**
 * Secciones del curso del docente. Las comparten las pestañas de la cabecera
 * del curso y el menú lateral, que despliega las del curso abierto.
 *
 * Cada sección es su propia ruta; «Estudiantes» es la ficha del curso
 * (/docente/cursos/{id}) y por eso es la pestaña inicial.
 */

export type SeccionCurso = 'programa' | 'estudiantes' | 'actividades' | 'mensajes' | 'biblioteca' | 'gestion';

export interface EnlaceSeccion {
    id: SeccionCurso;
    label: string;
    href: string;
}

export function seccionesCurso(idCurso: number | string): EnlaceSeccion[] {
    const base = `/docente/cursos/${idCurso}`;
    return [
        { id: 'programa', label: 'Programa', href: `${base}/programa` },
        { id: 'estudiantes', label: 'Estudiantes', href: base },
        { id: 'actividades', label: 'Actividades', href: `${base}/actividades` },
        { id: 'mensajes', label: 'Mensajes', href: `${base}/mensajeria` },
        { id: 'biblioteca', label: 'Biblioteca', href: `${base}/biblioteca` },
        { id: 'gestion', label: 'Gestión', href: `${base}/gestion` },
    ];
}

/**
 * Sección del curso a la que pertenece la URL, o null si la URL no es de ese
 * curso. Las páginas que cuelgan de Gestión (equipo, delegación, asistente
 * del syllabus) marcan Gestión.
 */
export function seccionDeUrl(url: string, idCurso: number | string): SeccionCurso | null {
    const ruta = url.split(/[?#]/)[0].replace(/\/+$/, '');
    const base = `/docente/cursos/${idCurso}`;
    if (ruta === base) return 'estudiantes';
    if (!ruta.startsWith(base + '/')) return null;
    const resto = ruta.slice(base.length + 1);
    if (resto === 'programa/editar') return 'gestion';
    const primero = resto.split('/')[0];
    switch (primero) {
        case 'programa':
            return 'programa';
        case 'actividades':
            return 'actividades';
        case 'mensajeria':
        case 'mensajes':
            return 'mensajes';
        case 'biblioteca':
            return 'biblioteca';
        case 'gestion':
        case 'docentes':
        case 'team':
        case 'delegacion-permisos':
        case 'permisos-syllabus':
            return 'gestion';
        default:
            return 'estudiantes';
    }
}

/** «Segundo semestre 2026». */
export function nombreSemestre(semestre: number | null | undefined, agno: number | null | undefined): string {
    if (!agno) return 'Sin período';
    const nombre = semestre === 1 ? 'Primer semestre' : semestre === 2 ? 'Segundo semestre' : `Semestre ${semestre ?? ''}`.trim();
    return `${nombre} ${agno}`;
}
