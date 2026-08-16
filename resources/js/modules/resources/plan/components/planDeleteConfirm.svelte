<script lang="ts">
  /**
   * planDeleteConfirm — Confirmación de eliminación de un plan de estudio.
   *
   * Totalmente controlado: el padre ejecuta deletePlan() en onConfirm. El
   * backend rechaza el borrado si el plan tiene asignaturas en su malla.
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import type { Plan } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    /** Plan a eliminar; se identifica en el diálogo. */
    plan?: Plan | null;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  // isOpen es $bindable porque ConfirmationModal lo muta al cerrarse; sin
  // ello sería una mutación de prop no-bindable (warning de ownership).
  let {
    isOpen = $bindable(false),
    plan = null,
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();

  const etiqueta = $derived(
    plan ? `${plan.carrera?.nombre ?? 'Plan'} · ${plan.agno_plan} v${plan.version_plan}` : null,
  );
</script>

<ConfirmationModal
  bind:isOpen
  tone="danger"
  title="Eliminar plan de estudio"
  recordName={etiqueta}
  recordMeta={plan?.creditos_sct_totales ? [`${plan.creditos_sct_totales} créditos SCT`] : []}
  message="El plan se elimina de forma definitiva. Si tiene asignaturas en su malla, el sistema no permitirá eliminarlo: vacía antes la malla."
  confirmLabel="Eliminar plan"
  {onCancel}
  {onConfirm}
/>
