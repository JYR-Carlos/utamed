<script lang="ts">
  /**
   * departamentoDeleteConfirm — Confirmación de discontinuación de un
   * departamento.
   *
   * Totalmente controlado: no hace HTTP; el padre ejecuta
   * deleteDepartamento() en onConfirm. El backend rechaza si el
   * departamento aún tiene carreras, y el diálogo lo dice antes.
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import type { Departamento } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    /** Departamento afectado; se identifica en el diálogo. */
    departamento?: Departamento | null;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    departamento = null,
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();

  const carreras = $derived(departamento?.carreras_count ?? departamento?.carreras?.length ?? 0);
</script>

<ConfirmationModal
  bind:isOpen
  tone="danger"
  title="Eliminar departamento"
  recordName={departamento?.nombre ?? null}
  recordMeta={[departamento?.facultad?.nombre ?? '']}
  message="El departamento se elimina de forma definitiva."
  confirmPhrase={departamento?.nombre ?? null}
  confirmLabel="Eliminar departamento"
  {onCancel}
  {onConfirm}
>
  {#if carreras}
    <div class="rounded-lg p-3 text-[13px] leading-relaxed bg-[var(--state-warn-soft)] text-[var(--state-warn)]">
      Este departamento tiene {carreras} carrera{carreras === 1 ? '' : 's'} asociada{carreras === 1
        ? ''
        : 's'} y el sistema no permitirá eliminarlo. Traslada antes sus carreras a otro
      departamento.
    </div>
  {/if}
</ConfirmationModal>
