import { BookOpen, Building2, ClipboardList, GraduationCap } from 'lucide-svelte';

/**
 * Ambientes (roles) entre los que puede moverse una persona. Una misma cuenta
 * puede ser docente, estudiante y ayudante a la vez; el menú muestra uno.
 * Lo usan el menú general (RoleSidebar) y el del alumno (MenuAlumno).
 */
export type Ambiente = 'docente' | 'jefe' | 'estudiante' | 'ayudante' | 'admin';

export const AMBIENTES: Record<Ambiente, { label: string; icon: typeof BookOpen; href: string }> = {
    docente: { label: 'Docente', icon: GraduationCap, href: '/docente/dashboard' },
    jefe: { label: 'Jefe de Carrera', icon: ClipboardList, href: '/docente/jefe-carrera/dashboard' },
    estudiante: { label: 'Estudiante', icon: GraduationCap, href: '/estudiante/dashboard' },
    ayudante: { label: 'Ayudantía', icon: BookOpen, href: '/ayudante/dashboard' },
    admin: { label: 'Administración', icon: Building2, href: '/admin/dashboard' },
};

interface AuthAmbientes {
    roles?: string[];
    is_super_admin?: boolean;
    docente?: unknown;
    estudiante?: unknown;
    ayudante_courses?: unknown[];
}

/** Ambientes disponibles en orden de prioridad, según perfiles y permisos (no nombres de rol, salvo jefe). */
export function ambientesDisponibles(auth: AuthAmbientes | undefined, can: (permiso: string) => boolean): Ambiente[] {
    const esDocente = (auth?.docente ?? null) !== null && can('cursos:ver');
    const esJefe = (auth?.roles ?? []).includes('Jefe de Carrera');
    const esEstudiante = (auth?.estudiante ?? null) !== null;
    const esAyudante = (auth?.ayudante_courses ?? []).length > 0;
    const esAdmin =
        Boolean(auth?.is_super_admin) ||
        can('facultades:ver') ||
        can('departamentos:ver') ||
        can('usuarios/permisos/roles:ver');

    return (
        [
            esDocente && 'docente',
            esJefe && 'jefe',
            esEstudiante && 'estudiante',
            esAyudante && 'ayudante',
            esAdmin && 'admin',
        ] as Array<Ambiente | false>
    ).filter((a): a is Ambiente => a !== false);
}
