import '@inertiajs/svelte';
import { type Docente } from './admin.types';

// ─── Course Types ───────────────────────────────────────────────────────────

export interface Asignatura {
    id_asignatura?: number;
    nombre?: string;
    creditos_sct?: number;
    horas_catedra?: number;
    horas_taller?: number;
    horas_laboratorio?: number;
}

export interface Carrera {
    id_carrera?: number;
    nombre?: string;
}

export interface Curso {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    cod_asignatura?: string;
    asignatura_nombre?: string;
    carrera_nombre?: string;
    imagen_url?: string;
    fecha_inicio?: string;
    fecha_fin?: string;
    semestre_real?: number;
    agno_real?: number;
    letra_grupo?: string;
    total_estudiantes?: number;
    asignatura?: Asignatura | null;
    carrera?: Carrera | null;
}

export interface SidebarCourse {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    tiene_programa?: boolean;
    userPermissions?: Array<{ id_permiso: number; slug: string; esta_permitido: boolean }>;
}

export interface SharedAuth {
    user: User;
    roles: string[];
    is_super_admin: boolean;
    permissions: string[];
    docente?: any;
    estudiante?: any;
    docente_courses: SidebarCourse[];
    estudiante_courses: SidebarCourse[];
    ayudante_courses: SidebarCourse[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: any;
    isActive?: boolean;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: SharedAuth;
    [key: string]: unknown;
};

export interface User {
    id_usuario: number;
    username: string;
    email: string;
    nombre1: string;
    nombre2?: string;
    apellido1: string;
    apellido2?: string;
    rut: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    docente?: Docente;
}

export type BreadcrumbItemType = BreadcrumbItem;
