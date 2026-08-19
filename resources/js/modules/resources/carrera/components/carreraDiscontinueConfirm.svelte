<script lang="ts">
  /**
   * carreraDiscontinueConfirm — Confirmación de discontinuación de una
   * carrera, con explicación del impacto: deja de admitir planes nuevos
   * pero preserva el historial académico.
   *
   * La explicación describe consecuencias, no implementación: antes decía
   * «establecerá una fecha de eliminación (Soft Delete)», que es cómo lo
   * guarda la base de datos, no lo que le ocurre a la carrera.
   *
   * Totalmente controlado: el padre ejecuta discontinueCarrera() en
   * onConfirm.
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import type { Carrera } from '@/types/admin.types';

  interface Props {
    isOpen: boolean;
    /** Carrera a discontinuar; se identifica en el diálogo. */
    carrera?: Carrera | null;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(),
    carrera = null,
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();

  const meta = $derived(
    carrera
      ? [
          carrera.departamento?.nombre ?? '',
          carrera.planes_activos_count
            ? `${carrera.planes_activos_count} plan${carrera.planes_activos_count === 1 ? '' : 'es'} activo${carrera.planes_activos_count === 1 ? '' : 's'}`
            : '',
        ]
      : [],
  );
</script>

<ConfirmationModal
  bind:isOpen
  tone="warning"
  title="Discontinuar carrera"
  recordName={carrera?.nombre ?? null}
  recordMeta={meta}
  {isLoading}
  confirmLabel="Discontinuar carrera"
  {onCancel}
  {onConfirm}
>
  <div
    class="rounded-lg p-4 text-[13px] leading-relaxed bg-[var(--state-info-soft)] text-[var(--state-info)]"
  >
    <p class="font-semibold mb-1">Qué ocurre al discontinuarla</p>
    <ul class="list-disc pl-4 space-y-1">
      <li>La carrera deja de admitir planes de estudio nuevos.</li>
      <li>El historial académico de los estudiantes actuales se conserva intacto.</li>
      <li>
        La carrera sigue visible en el sistema con estado <strong>Discontinuada</strong>.
      </li>
    </ul>
  </div>
</ConfirmationModal>
