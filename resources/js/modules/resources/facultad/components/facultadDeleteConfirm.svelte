<script lang="ts">
  /**
   * facultadDeleteConfirm — Confirmación de eliminación de una facultad.
   *
   * Totalmente controlado: el padre ejecuta deleteFacultad() en onConfirm.
   * El backend rechaza el borrado si la facultad tiene departamentos.
   */
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';

  interface Props {
    isOpen?: boolean;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();
</script>

<DeleteConfirmation
  bind:isOpen
  title="¿Eliminar Facultad?"
  message="Esta acción no se puede deshacer. Si la facultad tiene departamentos asociados, no podrá ser eliminada."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
