<script lang="ts">
  /**
   * Componente: Confirmación de Eliminación de Departamento
   *
   * Diálogo modal para confirmar la eliminación (soft delete) de un departamento.
   *
   * Props:
   * - isOpen: boolean para controlar visibilidad
   * - isLoading: boolean para estado de carga
   * - onConfirm: callback cuando se confirma la eliminación
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
  title="¿Eliminar Departamento?"
  message="Esta acción no se puede deshacer. Si el departamento tiene carreras asociadas, no podrá ser eliminado."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
