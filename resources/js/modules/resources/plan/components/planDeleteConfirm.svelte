<script lang="ts">
  /**
   * planDeleteConfirm — Confirmación de eliminación de un plan.
   *
   * Totalmente controlado: el padre ejecuta deletePlan() en onConfirm. El
   * backend rechaza el borrado si el plan tiene asignaturas en su malla.
   */
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';

  interface Props {
    isOpen?: boolean;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  // isOpen es $bindable porque DeleteConfirmation lo muta al cerrarse; sin
  // ello sería una mutación de prop no-bindable (warning de ownership).
  let {
    isOpen = $bindable(false),
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();
</script>

<DeleteConfirmation
  bind:isOpen
  title="¿Eliminar Plan?"
  message="Esta acción no se puede deshacer. Si el plan tiene asignaturas asignadas, no podrá ser eliminado."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
