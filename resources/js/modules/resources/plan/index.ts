/**
 * Módulo Plan — barrel principal.
 *
 * Planes curriculares por carrera/año/versión. La composición de la malla
 * (asignaturas por año/semestre) se edita en el módulo detalle-malla; aquí
 * solo se visualiza vía MallaSlideOver.
 */
export { PlanList, PlanForm, PlanDeleteConfirm, MallaSlideOver } from './components';
export * from './services/planApi';
