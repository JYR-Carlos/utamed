/**
 * Tipos específicos del módulo Curso
 * Re-exporta tipos base de admin.types y define extensiones/derivados
 */

import type { Curso, Seccion, TipoSeccion, Docente, Asignatura, Plan, PaginatedResponse, CursoFormData, AsignacionPlan } from '@/types/admin.types';

export type { Curso, Seccion, TipoSeccion, Docente, Asignatura, Plan, PaginatedResponse, CursoFormData, AsignacionPlan };

/**
 * Datos del formulario de creación/edición de sección
 */
export interface SeccionFormState {
    id_tipo_seccion: number;
    id_docente?: number;
}

/**
 * Respuesta paginada para cursos
 */
export type CursosPaginatedResponse = PaginatedResponse<Curso>;
