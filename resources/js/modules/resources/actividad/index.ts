/**
 * Módulo Actividad — barrel principal.
 *
 * Actividades evaluables de un curso (sumativas/formativas, grupales o
 * individuales). El CRUD es del docente; la vista del estudiante tiene sus
 * propios componentes (pages/student).
 */

// Componentes
export { ActividadForm } from './components';

// Services
export {
  createActividad,
  updateActividad,
  deleteActividad,
  toggleVisibilidadActividad,
  updateGrupo,
} from './services/actividadApi';
