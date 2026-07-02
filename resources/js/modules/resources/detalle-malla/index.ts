/**
 * Módulo Detalle de Malla — barrel principal.
 *
 * Editor de la malla curricular de un plan: catálogo de asignaturas a la
 * izquierda (AsignaturasCatalogo) y grilla año/semestre a la derecha
 * (MallaGrid), con modales para reposicionar o quitar asignaciones.
 */
export { default as AsignaturasCatalogo } from './components/asignaturasCatalogo.svelte';
export { default as MallaGrid } from './components/mallaGrid.svelte';
export { default as EditAsignacionModal } from './components/editAsignacionModal.svelte';
export { default as AsignacionDeleteConfirm } from './components/asignacionDeleteConfirm.svelte';
export * from './types/mallaCurricular.types';
export * from './services/mallaApi';
