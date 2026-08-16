<script lang="ts">
  /**
   * actividadDeleteConfirm — Confirmación de borrado de una actividad.
   *
   * Totalmente controlado: no hace HTTP (a diferencia de otros deleteConfirm
   * del proyecto); el padre ejecuta deleteActividad() en onConfirm y decide
   * cuándo cerrar.
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import type { Actividad } from '@/types/actividad';

  interface Props {
    isOpen?: boolean;
    /** Actividad afectada; se identifica en el diálogo. */
    actividad?: Actividad | null;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    actividad = null,
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();
</script>

<ConfirmationModal
  bind:isOpen
  tone="danger"
  title="Eliminar actividad"
  recordName={actividad?.nombre ?? null}
  recordMeta={actividad?.fecha_limite ? [`Fecha límite ${actividad.fecha_limite}`] : []}
  message="Se eliminan también las entregas y notas que los estudiantes ya tengan en esta actividad. No se puede deshacer."
  confirmLabel="Eliminar actividad"
  {onConfirm}
  {onCancel}
  {isLoading}
/>
