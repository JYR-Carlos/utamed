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
  title="¿Eliminar Actividad?"
  message="Esta acción no se puede deshacer. Todos los datos relacionados a esta actividad serán eliminados."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
