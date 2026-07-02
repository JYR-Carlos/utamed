/**
 * Módulo Asignatura — barrel principal.
 *
 * Catálogo global de asignaturas con versionado: editar una asignatura crea
 * una nueva versión y marca la anterior como histórica. Lo consumen la vista
 * de admin y la de jefe de carrera (misma página, distinto routePrefix).
 */

// Componentes
export { AsignaturaList, AsignaturaForm, AsignaturaDeleteConfirm } from './components';

// Tipos
export type { Asignatura, PaginatedResponse, AsignaturaFormData } from './types/asignatura.types';
