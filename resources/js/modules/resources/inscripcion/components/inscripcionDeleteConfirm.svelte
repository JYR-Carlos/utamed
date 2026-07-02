<script lang="ts">
  /**
   * inscripcionDeleteConfirm — Confirmación de borrado definitivo de una
   * inscripción; el mensaje sugiere preferir los estados Retirado/Anulado
   * para conservar el historial.
   *
   * Totalmente controlado: el padre ejecuta deleteInscripcion() en onConfirm.
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
  title="¿Eliminar inscripción?"
  message="Esta acción es permanente. Considera cambiar el estado a 'Retirado' o 'Anulado' en su lugar para mantener el historial."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
