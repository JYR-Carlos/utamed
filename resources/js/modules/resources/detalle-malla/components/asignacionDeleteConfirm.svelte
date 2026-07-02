<script lang="ts">
  /**
   * asignacionDeleteConfirm — Confirmación para quitar una asignatura de la
   * malla de un plan (elimina la asignación, no la asignatura del catálogo).
   *
   * Totalmente controlado: el padre ejecuta deleteAsignacion() en onConfirm.
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
  title="¿Quitar Asignatura del Plan?"
  message="La asignatura será removida de esta malla. Esta acción no se puede deshacer."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
