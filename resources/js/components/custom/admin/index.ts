/**
 * Admin shared UI primitives
 * Generic reusable wrapper components — not resource-specific.
 * Resource-specific components live in @/modules/resources/{resource}/components/
 */

export { default as DataTable } from './DataTable.svelte';
// La confirmación vive en @/components/admin/ConfirmationModal.svelte, base
// única del panel: identifica el registro afectado y admite escribir para
// confirmar. DeleteConfirmation se retiró al unificar las tres bases.
export { default as FormModal } from './FormModal.svelte';
export { default as PermissionsModal } from './permissions-modal/PermissionsModal.svelte';
