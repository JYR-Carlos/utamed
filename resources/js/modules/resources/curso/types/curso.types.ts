/**
 * Tipos específicos del módulo Curso
 * Re-exporta tipos base de admin.types y define extensiones/derivados
 */

import type { Curso, Componente, TipoComponente, Docente, Asignatura, Plan, PaginatedResponse, CursoFormData, AsignacionPlan, ComponenteFormState } from '@/types/admin.types';

export type { Curso, Componente, TipoComponente, Docente, Asignatura, Plan, PaginatedResponse, CursoFormData, AsignacionPlan, ComponenteFormState };

/**
 * Respuesta paginada para cursos
 */
export type CursosPaginatedResponse = PaginatedResponse<Curso>;
