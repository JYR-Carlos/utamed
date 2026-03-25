<script lang="ts">
  /**
   * Componente: Confirmación de Eliminación de Facultad
   *
   * Dialog de confirmación antes de eliminar una facultad.
   * Reutilizable en diferentes contextos.
   *
   * Props:
   * - isOpen: boolean controla visibilidad
   * - isLoading: boolean para mostrar estado loading
   * - onConfirm: callback cuando se confirma eliminación
   * - onCancel: callback para cancelar
   */
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
  title="¿Eliminar Facultad?"
  message="Esta acción no se puede deshacer. Si la facultad tiene departamentos asociados, no podrá ser eliminada."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
