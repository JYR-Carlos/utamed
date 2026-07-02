/**
 * Módulo Actividad — barrel principal.
 *
 * Actividades evaluables de un curso (sumativas/formativas, grupales o
 * individuales). El CRUD es del docente; el estudiante solo consume
 * ActividadCard en su vista de actividades.
 */

// Componentes
export { ActividadList, ActividadForm, ActividadDeleteConfirm, ActividadCard } from './components';

// Services
export { createActividad, updateActividad, deleteActividad } from './services/actividadApi';
