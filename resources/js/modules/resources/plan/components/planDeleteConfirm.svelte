<script lang="ts">
  /**
   * Componente confirmación de eliminación de plan.
   *
   * Wrapper alrededor del DeleteConfirmation genérico con mensaje específico para planes.
   */
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';

  interface Props {
    isOpen: boolean;
    isLoading: boolean;
    onConfirm: () => void;
    onCancel: () => void;
  }

  let { isOpen, isLoading, onConfirm, onCancel }: Props = $props();
</script>

<DeleteConfirmation
  bind:isOpen
  title="¿Eliminar Plan?"
  message="Esta acción no se puede deshacer. Si el plan tiene asignaturas asignadas, no podrá ser eliminado."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
