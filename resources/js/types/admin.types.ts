/**
 * Type definitions for the admin module
 * Contains interfaces for all administrative entities, form data, and pagination
 * 
 * @module admin.types
 */

import type { FormDataConvertible } from '@inertiajs/core';

/**
 * Facultad (Faculty) entity
 * Represents an academic faculty within the university
 */
export interface Facultad {
    /** Unique identifier for the faculty */
    id_facultad: number;
    /** Name of the faculty */
    nombre: string;
    /** Creation timestamp */
    fecha_creacion?: string;
    /** Last modification timestamp */
    fecha_modificacion?: string;
    /** Soft delete timestamp */
    fecha_eliminacion?: string;
}

/**
 * Departamento (Department) entity
 * Represents an academic department within a faculty
 */
export interface Departamento {
    /** Unique identifier for the department */
    id_departamento: number;
    /** Foreign key to the parent faculty */
    id_facultad: number;
    /** Name of the department */
    nombre: string;
    /** Related faculty object (eager loaded) */
    facultad?: Facultad;
    /** Creation timestamp */
    fecha_creacion?: string;
    /** Last modification timestamp */
    fecha_modificacion?: string;
    /** Soft delete timestamp */
    fecha_eliminacion?: string;
}

export interface Carrera {
    id_carrera: number;
    nombre: string;
    jornada?: string;
    sede?: string;
    modalidad?: string;
    id_departamento: number;
    id_facultad: number;
    departamento?: Departamento;
    fecha_creacion?: string;
    fecha_modificacion?: string;
    fecha_eliminacion?: string;
}

export interface Plan {
    id_plan: number;
    id_carrera: number;
    agno: number;
    version: number;
    creditos_sct_totales?: number;
    carrera?: Carrera;
    fecha_creacion?: string;
    fecha_modificacion?: string;
    fecha_eliminacion?: string;
}

/**
 * Asignatura (Subject/Course) entity
 * Represents an academic subject with its credit and hour distribution
 */
export interface Asignatura {
    /** Unique identifier for the subject */
    id_asignatura: number;
    /** Subject code (e.g., "MED101") */
    cod_asignatura: string;
    /** Subject name */
    nombre: string;
    /** Subject description */
    descripcion?: string;
    /** SCT (Sistema de Créditos Transferibles) credits */
    creditos_sct?: number;
    /** Lecture hours */
    horas_catedra?: number;
    /** Workshop hours */
    horas_taller?: number;
    /** Laboratory hours */
    horas_laboratorio?: number;
    /** Directed study hours */
    horas_dirigidas?: number;
    /** Autonomous study hours */
    horas_autonomas?: number;
    /** Creation timestamp */
    fecha_creacion?: string;
    /** Last modification timestamp */
    fecha_modificacion?: string;
    /** Soft delete timestamp */
    fecha_eliminacion?: string;
}

export interface AsignacionPlan {
    id_asignacion: number;
    id_plan: number;
    id_asignatura: number;
    agno_planificado: number;
    semestre_planificado: number;
    tipo_ramo?: string;
    asignatura?: Asignatura;
    plan?: Plan;
    fecha_creacion?: string;
    fecha_modificacion?: string;
    fecha_eliminacion?: string;
}

export interface AsignacionPlanFormData {
    id_asignatura: number;
    agno_planificado: number;
    semestre_planificado: number;
    tipo_ramo?: string;
}

export interface MallaData {
    [key: string]: AsignacionPlan[]; // key format: "año-semestre"
}

export interface Curso {
    id_curso: number;
    id_asignatura: number;
    id_plan: number;
    cod_curso: number;
    nombre?: string;
    fecha_inicio?: string;
    numero_semestre?: number;
    id_docente?: number;
    asignatura?: Asignatura;
    plan?: Plan;
    docente?: Docente;
    fecha_creacion?: string;
    fecha_modificacion?: string;
    fecha_eliminacion?: string;
}

/**
 * TipoSeccion (Section Type) entity
 * Represents the type of a course section (e.g., Cátedra, Problemas, Laboratorio)
 */
export interface TipoSeccion {
    /** Unique identifier for the section type */
    id_tipo_seccion: number;
    /** Type name (e.g., "Cátedra", "Problemas", "Laboratorio") */
    tipo: string;
    /** Creation timestamp */
    fecha_creacion?: string;
    /** Last modification timestamp */
    fecha_modificacion?: string;
}

/**
 * Seccion (Course Section) entity
 * Represents a specific section of a course (e.g., lecture, lab, workshop)
 */
export interface Seccion {
    /** Unique identifier for the section */
    id_seccion: number;
    /** Foreign key to the parent course */
    id_curso: number;
    /** Foreign key to the section type */
    id_tipo_seccion: number;
    /** Foreign key to the assigned instructor (optional) */
    id_docente?: number;
    /** Related section type object (eager loaded) */
    tipo_seccion?: TipoSeccion;
    /** Related instructor object (eager loaded) */
    docente?: Docente;
    /** Creation timestamp */
    fecha_creacion?: string;
    /** Last modification timestamp */
    fecha_modificacion?: string;
    /** Soft delete timestamp */
    fecha_eliminacion?: string;
}

export interface Usuario {
    id_usuario: number;
    username: string;
    fecha_creacion?: string;
}

export interface Estudiante {
    id_estudiante: number;
    rut: string;
    nombre1: string;
    nombre2?: string;
    apellido1: string;
    apellido2?: string;
    email?: string;
    agno_ingreso?: number;
    id_carrera?: number;
    carrera?: Carrera;
}

export interface Docente {
    id_docente: number;
    rut: string;
    nombre1: string;
    nombre2?: string;
    apellido1: string;
    apellido2?: string;
    nombre_completo?: string;
    email?: string;
    grado?: string;
    titulo?: string;
    cargo?: string;
}

export interface Administrador {
    id_usuario: number;
    rut: string;
    nombre1: string;
    nombre2?: string;
    apellido1: string;
    apellido2?: string;
    email?: string;
    username: string;
    esta_activo: boolean;
}

/**
 * Generic paginated response from Laravel
 * Used for all paginated data tables and lists
 * 
 * @template T - The type of data being paginated
 */
export interface PaginatedResponse<T> {
    /** Array of data items for the current page */
    data: T[];
    /** Current page number */
    current_page: number;
    /** Total number of pages */
    last_page: number;
    /** Number of items per page */
    per_page: number;
    /** Total number of items across all pages */
    total: number;
    /** Index of first item on current page */
    from: number;
    /** Index of last item on current page */
    to: number;
    /** URL for the previous page */
    prev_page_url?: string;
    /** URL for the next page */
    next_page_url?: string;
    /** Pagination links for navigation */
    links: {
        url?: string;
        label: string;
        active: boolean;
    }[];
}

/**
 * Form data for creating/updating a Facultad
 * Used in FormModal components for faculty management
 */
export interface FacultadFormData {
    /** Faculty name */
    nombre: string;
    /** Allow additional form fields */
    [key: string]: FormDataConvertible;
}

export interface DepartamentoFormData {
    nombre: string;
    id_facultad: number;
    [key: string]: FormDataConvertible;
}

export interface CarreraFormData {
    nombre: string;
    jornada?: string;
    sede?: string;
    modalidad?: string;
    id_departamento: number;
    id_facultad: number;
    [key: string]: FormDataConvertible;
}

export interface PlanFormData {
    id_carrera: number;
    agno: number;
    version: number;
    creditos_sct_totales?: number;
    [key: string]: FormDataConvertible;
}

/**
 * Form data for creating/updating an Asignatura
 * Contains all editable fields for subject management
 */
export interface AsignaturaFormData {
    /** Subject code */
    cod_asignatura: string;
    /** Subject name */
    nombre: string;
    /** Subject description */
    descripcion?: string;
    /** SCT credits */
    creditos_sct?: number;
    /** Lecture hours */
    horas_catedra?: number;
    /** Workshop hours */
    horas_taller?: number;
    /** Laboratory hours */
    horas_laboratorio?: number;
    /** Directed study hours */
    horas_dirigidas?: number;
    /** Autonomous study hours */
    horas_autonomas?: number;
    /** Allow additional form fields */
    [key: string]: FormDataConvertible;
}

export interface AsignacionPlanFormData {
    id_asignatura: number;
    agno_planificado: number;
    semestre_planificado: number;
    tipo_ramo?: string;
    [key: string]: FormDataConvertible;
}

export interface CursoFormData {
    id_asignatura: number;
    id_plan: number;
    cod_curso: number;
    nombre?: string;
    fecha_inicio?: string;
    numero_semestre?: number;
    id_docente?: number;
    [key: string]: FormDataConvertible;
}

export interface EstudianteFormData {
    rut: string;
    nombre1: string;
    nombre2?: string;
    apellido1: string;
    apellido2?: string;
    email?: string;
    agno_ingreso?: number;
    id_carrera?: number;
    username: string;
    password: string;
    [key: string]: FormDataConvertible;
}

export interface DocenteFormData {
    rut: string;
    nombre1: string;
    nombre2?: string;
    apellido1: string;
    apellido2?: string;
    email?: string;
    grado?: string;
    titulo?: string;
    cargo?: string;
    username: string;
    password: string;
    [key: string]: FormDataConvertible;
}

export interface AdministradorFormData {
    rut: string;
    nombre1: string;
    nombre2?: string;
    apellido1: string;
    apellido2?: string;
    email?: string;
    username: string;
    password: string;
    [key: string]: FormDataConvertible;
}
