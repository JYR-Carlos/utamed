// Admin Types
import type { FormDataConvertible } from '@inertiajs/core';


export interface Facultad {
    id_facultad: number;
    nombre: string;
    fecha_creacion?: string;
    fecha_modificacion?: string;
    fecha_eliminacion?: string;
}

export interface Departamento {
    id_departamento: number;
    id_facultad: number;
    nombre: string;
    facultad?: Facultad;
    fecha_creacion?: string;
    fecha_modificacion?: string;
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

export interface Asignatura {
    id_asignatura: number;
    cod_asignatura: string;
    nombre: string;
    descripcion?: string;
    creditos_sct?: number;
    horas_catedra?: number;
    horas_taller?: number;
    horas_laboratorio?: number;
    horas_dirigidas?: number;
    horas_autonomas?: number;
    fecha_creacion?: string;
    fecha_modificacion?: string;
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
    cod_curso: string;
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

// Pagination
export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

// Form Data Types
export interface FacultadFormData {
    nombre: string;
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

export interface AsignaturaFormData {
    cod_asignatura: string;
    nombre: string;
    descripcion?: string;
    creditos_sct?: number;
    horas_catedra?: number;
    horas_taller?: number;
    horas_laboratorio?: number;
    horas_dirigidas?: number;
    horas_autonomas?: number;
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
    cod_curso: string;
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
