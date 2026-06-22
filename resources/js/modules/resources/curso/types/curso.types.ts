/**
 * Tipos específicos del módulo Curso
 * Re-exporta tipos base de admin.types y define extensiones/derivados
 */

import type { Curso, Componente, TipoComponente, Docente, Asignatura, Plan, PaginatedResponse, CursoFormData, AsignacionPlan, ComponenteFormState, DocenteAsignadoComponente } from '@/types/admin.types';

export type { Curso, Componente, TipoComponente, Docente, Asignatura, Plan, PaginatedResponse, CursoFormData, AsignacionPlan, ComponenteFormState, DocenteAsignadoComponente };

/** @deprecated Usar Componente */
export interface Seccion extends Componente {
    id_tipo_seccion?: number;
    id_docente?: number;
}

/** @deprecated Usar TipoComponente */
export interface TipoSeccion extends TipoComponente {
    id_tipo_seccion?: number;
}

/** @deprecated Usar ComponenteFormState */
export interface SeccionFormState {
    id_tipo_seccion: number;
    id_docente?: number;
}

/**
 * Respuesta paginada para cursos
 */
export type CursosPaginatedResponse = PaginatedResponse<Curso>;
