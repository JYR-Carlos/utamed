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
  title="¿Quitar Asignatura del Plan?"
  message="La asignatura será removida de esta malla. Esta acción no se puede deshacer."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
