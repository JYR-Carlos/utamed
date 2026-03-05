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
    /** Associated permission context identifier */
    id_contexto?: number;
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
    id_contexto?: number;
    departamento?: Departamento;
    fecha_creacion?: string;
    fecha_modificacion?: string;
    fecha_eliminacion?: string | null;
    /** Computed by the controller: count of plans without fecha_eliminacion */
    planes_activos_count?: number;
    /** Computed: true if a user with rol='Jefe de Carrera' has an active assignment in this carrera's context (tipo_contexto.categoria='carrera') */
    has_director?: boolean;
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
    /** Number of study plans using this subject (withCount) */
    planes_count?: number;
    /** Creation timestamp */
    fecha_creacion?: string;
    /** Last modification timestamp */
    fecha_modificacion?: string;
    /** Soft delete timestamp */
    fecha_eliminacion?: string;
}

export interface AsignacionPlan {
    id_asignacion_plan: number;
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

export interface Programa {
    /** Unique identifier for the programa */
    id_programa: number;
    /** Course this programa belongs to */
    id_curso: number;
    /** Whether this is a template programa (draft) */
    es_plantilla: boolean;
    /** Whether this is the current active programa */
    es_actual: boolean;
    /** Whether it is a draft or approved */
    estado: string;
    /** Version number (increments on regeneration) */
    version_programa: number;
    /** ISO timestamp of creation */
    fecha_creacion?: string;
    /** User who created the programa */
    creado_por?: number;
}

export interface Curso {
    id_curso: number;
    cod_curso: number;
    nombre?: string;
    asignatura_nombre?: string;
    cod_asignatura?: string;
    creditos_sct?: number;
    horas_catedra?: number;
    horas_taller?: number;
    horas_laboratorio?: number;
    horas_dirigidas?: number;
    horas_autonomas?: number;
    carrera_nombre?: string;
    docente_nombre?: string;
    docente_email?: string;
    docente_cargo?: string;
    fecha_inicio?: string;
    fecha_fin?: string;
    indice_grupo?: number;
    letra_grupo?: string;
    id_asignacion_plan: number;
    id_contexto: number;
    id_curso_padre?: number;
    version_plantilla?: number;
    numero_semestre?: number;
    asignacionPlan?: AsignacionPlan;
    fecha_creacion?: string;
    fecha_modificacion?: string;
    fecha_eliminacion?: string;
    // Programa information
    has_programa?: boolean;
    programa_estado?: string;
    id_programa?: number;
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
    rut?: string;
    nombre1?: string;
    nombre2?: string;
    apellido1?: string;
    apellido2?: string;
    email?: string;
    esta_activo?: boolean;
    fecha_creacion?: string;
}

export interface Estudiante {
    id_estudiante: number;
    id_usuario: number;
    agno_ingreso?: number;
    id_carrera?: number;
    carrera?: Carrera;
    usuario?: Usuario;
    /** @deprecated Use usuario.rut */
    rut?: string;
    /** @deprecated Use usuario.nombre1 */
    nombre1?: string;
    /** @deprecated Use usuario.nombre2 */
    nombre2?: string;
    /** @deprecated Use usuario.apellido1 */
    apellido1?: string;
    /** @deprecated Use usuario.apellido2 */
    apellido2?: string;
    /** @deprecated Use usuario.email */
    email?: string;
}

export interface Docente {
    id_docente: number;
    id_usuario?: number;
    rut?: string;
    nombre1?: string;
    nombre2?: string;
    apellido1?: string;
    apellido2?: string;
    nombre_completo?: string;
    email?: string;
    grado?: string;
    titulo?: string;
    cargo?: string;
    usuario?: Usuario;
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

// ─── Unified contract emitted by UsuarioResource ─────────────────────────────

/**
 * Flat user data sub-object serialized by UsuarioResource.
 * Matches the output of UsuarioResource::serializeUsuario().
 */
export interface UsuarioData {
    id_usuario: number;
    username: string;
    email?: string;
    rut?: string;
    nombre1?: string;
    nombre2?: string;
    apellido1?: string;
    apellido2?: string;
    esta_activo: boolean;
    fecha_verificacion_email?: string;
}

/**
 * Estudiante sub-object serialized by UsuarioResource.
 * Matches the output of UsuarioResource::serializeEstudiante().
 */
export interface EstudianteData {
    id_estudiante: number;
    id_usuario: number;
    agno_ingreso?: number;
    id_carrera?: number;
    carrera?: {
        id_carrera: number;
        nombre: string;
    };
}

/**
 * Docente sub-object serialized by UsuarioResource.
 * Matches the output of UsuarioResource::serializeDocente().
 */
export interface DocenteData {
    id_docente: number;
    id_usuario: number;
    grado?: string;
    titulo?: string;
    cargo?: string;
}

/**
 * Unified user item produced by UsuarioResource for every user type.
 * Shape is identical regardless of tipo (estudiante / docente / administrador).
 *
 * - `usuario`    always present
 * - `estudiante` present if the user has a student profile, null otherwise
 * - `docente`    present if the user has a teacher profile, null otherwise
 */
export interface UsuarioItem {
    usuario: UsuarioData;
    estudiante: EstudianteData | null;
    docente: DocenteData | null;
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
    agno_real?: number;
    semestre_real?: number;
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
