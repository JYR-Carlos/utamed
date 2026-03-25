/**
 * Barrel export para el módulo de Departamentos
 *
 * Centraliza la exportación de componentes y servicios relacionados con departamentos
 * para facilitar imports concisos en otras partes de la aplicación.
 *
 * Uso:
 *   import { DepartamentoList, departamentoApi } from '@/modules/resources/departamento';
 */

// Componentes
export { default as DepartamentoList } from './components/departamentoList.svelte';
export { default as DepartamentoForm } from './components/departamentoForm.svelte';
export { default as DepartamentoDeleteConfirm } from './components/departamentoDeleteConfirm.svelte';

// Servicios
export { createDepartamento, updateDepartamento, deleteDepartamento } from './services/departamentoApi';
export type { } from './services/departamentoApi';
