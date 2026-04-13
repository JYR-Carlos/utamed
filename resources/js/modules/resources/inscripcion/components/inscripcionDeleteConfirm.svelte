<script lang="ts">
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';

  interface Props {
    isOpen?: boolean;
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
