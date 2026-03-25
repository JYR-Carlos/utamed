<script lang="ts">
  /**
   * Componente: Confirmación Discontinuar Carrera
   *
   * Modal de confirmación para discontinuar (soft delete) una carrera.
   * Muestra advertencia sobre el impacto de la acción.
   *
   * Props:
   * - isOpen: boolean - Control de visibilidad
   * - carrera: Carrera | null - Carrera a discontinuar
   * - isLoading: boolean - Estado de carga
   * - onConfirm: () => void - Callback al confirmar
   * - onCancel: () => void - Callback al cancelar
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import type { Carrera } from '@/types/admin.types';

  interface Props {
    isOpen: boolean;
    carrera?: Carrera | null;
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
</script>

<ConfirmationModal
  bind:isOpen
  icon="warning"
  title="Discontinuar Carrera"
  message={carrera?.nombre || ''}
  {isLoading}
  isDangerous={true}
  confirmLabel="Confirmar Discontinuación"
  {onCancel}
  {onConfirm}
>
  <div
    class="flex gap-3 bg-blue-50 border border-blue-100 rounded-lg p-4 text-[13px] text-blue-800 leading-relaxed"
  >
    <svg
      class="shrink-0 mt-0.5 text-blue-500"
      xmlns="http://www.w3.org/2000/svg"
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line
        x1="12"
        y1="16"
        x2="12.01"
        y2="16"
      /></svg
    >
    <div>
      <p class="font-semibold mb-1">Impacto de esta acción</p>
      <p>
        Esta acción establecerá una <strong>fecha de eliminación (Soft Delete)</strong>. La carrera
        no admitirá nuevos planes, pero el historial académico de los estudiantes actuales se
        mantendrá intacto. La carrera seguirá visible en el sistema con estado
        <strong>Discontinuada</strong>.
      </p>
    </div>
  </div>
</ConfirmationModal>
