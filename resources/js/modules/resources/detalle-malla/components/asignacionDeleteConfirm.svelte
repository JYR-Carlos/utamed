<script lang="ts">
  /**
   * asignacionDeleteConfirm — Confirmación para quitar una asignatura de la
   * malla de un plan.
   *
   * Quita la asignación, no la asignatura del catálogo: el mensaje lo dice
   * explícitamente porque «Eliminar» en esta pantalla se leía como borrar
   * la asignatura entera.
   *
   * Totalmente controlado: el padre ejecuta deleteAsignacion() en onConfirm.
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import type { AsignacionPlan } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    /** Asignación afectada; se identifica en el diálogo. */
    asignacion?: AsignacionPlan | null;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    asignacion = null,
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();

  const meta = $derived(
    asignacion
      ? [
          asignacion.asignatura?.cod_asignatura ?? '',
          `Año ${asignacion.agno_planificado} · Semestre ${asignacion.semestre_planificado}`,
        ]
      : [],
  );
</script>

<ConfirmationModal
  bind:isOpen
  tone="warning"
  title="Quitar asignatura de la malla"
  recordName={asignacion?.asignatura?.nombre ?? null}
  recordMeta={meta}
  message="La asignatura sale de esta malla y sus créditos dejan de contar en el total del plan. La asignatura sigue existiendo en el catálogo y puedes volver a añadirla."
  confirmLabel="Quitar de la malla"
  {onConfirm}
  {onCancel}
  {isLoading}
/>
