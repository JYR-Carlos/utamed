<script lang="ts">
  /**
   * departamentoDeleteConfirm — Confirmación de discontinuación (soft
   * delete) de un departamento.
   *
   * Totalmente controlado: no hace HTTP; el padre ejecuta
   * deleteDepartamento() en onConfirm. El backend rechaza si el departamento
   * aún tiene carreras asociadas.
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
  title="¿Eliminar Departamento?"
  message="Esta acción no se puede deshacer. Si el departamento tiene carreras asociadas, no podrá ser eliminado."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
