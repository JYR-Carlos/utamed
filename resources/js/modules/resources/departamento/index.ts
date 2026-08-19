/**
 * Módulo Departamento — barrel principal.
 *
 * Departamentos académicos anidados bajo facultades, con carreras asociadas.
 * El borrado es discontinuación (soft delete) y se bloquea mientras el
 * departamento tenga carreras.
 */

// Componentes
export { default as DepartamentoList } from './components/departamentoList.svelte';
export { default as DepartamentoForm } from './components/departamentoForm.svelte';
export { default as DepartamentoDeleteConfirm } from './components/departamentoDeleteConfirm.svelte';

// Servicios
export { createDepartamento, updateDepartamento, deleteDepartamento } from './services/departamentoApi';
