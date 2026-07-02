<script lang="ts">
  /**
   * actividadDeleteConfirm — Confirmación de borrado de una actividad.
   *
   * Totalmente controlado: no hace HTTP (a diferencia de otros deleteConfirm
   * del proyecto); el padre ejecuta deleteActividad() en onConfirm y decide
   * cuándo cerrar.
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
  title="¿Eliminar Actividad?"
  message="Esta acción no se puede deshacer. Todos los datos relacionados a esta actividad serán eliminados."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
