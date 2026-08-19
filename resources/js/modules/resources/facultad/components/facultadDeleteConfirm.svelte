<script lang="ts">
  /**
   * facultadDeleteConfirm — Confirmación de eliminación de una facultad.
   *
   * Totalmente controlado: el padre ejecuta deleteFacultad() en onConfirm.
   * El backend rechaza el borrado si la facultad tiene departamentos, así
   * que el diálogo lo advierte ANTES de pedir la confirmación en vez de
   * dejar que el usuario descubra el rechazo al confirmar.
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import type { Facultad } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    /** Facultad a eliminar; se identifica en el diálogo. */
    facultad?: Facultad | null;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    facultad = null,
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();

  const departamentos = $derived(facultad?.departamentos?.length ?? 0);
</script>

<ConfirmationModal
  bind:isOpen
  tone="danger"
  title="Eliminar facultad"
  recordName={facultad?.nombre ?? null}
  recordMeta={departamentos ? [`${departamentos} departamento${departamentos === 1 ? '' : 's'}`] : []}
  message="La facultad se elimina de forma definitiva."
  confirmPhrase={facultad?.nombre ?? null}
  confirmLabel="Eliminar facultad"
  {onCancel}
  {onConfirm}
>
  {#if departamentos}
    <div class="rounded-lg p-3 text-[13px] leading-relaxed bg-[var(--state-warn-soft)] text-[var(--state-warn)]">
      Esta facultad tiene departamentos asociados y el sistema no permitirá
      eliminarla. Traslada o elimina antes sus departamentos.
    </div>
  {/if}
</ConfirmationModal>
