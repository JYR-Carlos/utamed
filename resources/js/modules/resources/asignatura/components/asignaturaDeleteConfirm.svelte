<script lang="ts">
  /**
   * asignaturaDeleteConfirm — Confirmación de borrado de una asignatura.
   *
   * Envía DELETE {routePrefix}/asignaturas/{id}. El backend responde siempre
   * con redirect + mensaje flash: 'success' al eliminar, 'error' si la
   * asignatura está asignada a planes. Ojo: para Inertia un redirect es una
   * visita exitosa, así que el modal se cierra en ambos casos y el resultado
   * lo comunica el flash; onError solo cubre errores de validación (422).
   */
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import { router } from '@inertiajs/svelte';
  import type { Asignatura } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    /** Asignatura a eliminar; null mientras el modal está cerrado. */
    deletingAsignatura?: Asignatura | null;
    /** Callback tras eliminar con éxito (el modal ya se cerró solo). */
    onSuccess?: () => void;
    /** Callback al cancelar; el padre es quien pone isOpen en false. */
    onCancel?: () => void;
    /** Prefijo base de rutas (p.ej. '/admin' o '/docente/jefe-carrera'). */
    routePrefix?: string;
  }

  let {
    isOpen = $bindable(false),
    deletingAsignatura = null,
    onSuccess = () => {},
    onCancel = () => {},
    routePrefix = '/admin',
  }: Props = $props();

  let isLoading = $state(false);

  function handleConfirm() {
    if (!deletingAsignatura) return;
    isLoading = true;
    router.delete(`${routePrefix}/asignaturas/${deletingAsignatura.id_asignatura}`, {
      onSuccess: () => {
        isOpen = false;
        isLoading = false;
        onSuccess();
      },
      onError: () => {
        isLoading = false;
      },
    });
  }
</script>

<DeleteConfirmation
  bind:isOpen
  title="¿Eliminar Asignatura?"
  message="Esta acción no se puede deshacer. Si la asignatura está asignada a planes, no podrá ser eliminada."
  onConfirm={handleConfirm}
  {onCancel}
  {isLoading}
/>
