<script lang="ts">
  /**
   * ConfirmDialog — Modal de confirmación accesible y reutilizable.
   *
   * Rescatado del modal `role="alertdialog"` de `ActividadEvaluacion.svelte`
   * (hallazgos D-01 / D-09) para reemplazar los `window.confirm()` nativos por
   * un diálogo accesible y consistente con el diseño del producto.
   *
   * Accesibilidad: `role="alertdialog"`, `aria-modal`, `aria-labelledby`,
   * `aria-describedby`, cierre con la tecla Escape y backdrop que cierra.
   */
  import { Trash2, AlertTriangle, Info } from 'lucide-svelte';

  interface Props {
    /** Controla la visibilidad del diálogo. */
    open: boolean;
    /** Título del diálogo (también es el `aria-labelledby`). */
    title: string;
    /** Cuerpo/descripción (también es el `aria-describedby`). */
    body: string;
    /** Texto del botón de confirmación. */
    confirmLabel?: string;
    /** Texto del botón de cancelación. */
    cancelLabel?: string;
    /** Estilo del diálogo: 'danger' (destructivo) o 'default'. */
    variant?: 'danger' | 'default';
    /** Callback al confirmar. */
    onConfirm: () => void;
    /** Callback al cancelar (backdrop, botón Cancelar o Escape). */
    onCancel: () => void;
  }

  let {
    open,
    title,
    body,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    variant = 'danger',
    onConfirm,
    onCancel
  }: Props = $props();

  const isDanger = $derived(variant === 'danger');

  // Cierre con tecla Escape mientras el diálogo está abierto.
  $effect(() => {
    if (!open) return;
    function onKeydown(e: KeyboardEvent) {
      if (e.key === 'Escape') onCancel();
    }
    window.addEventListener('keydown', onKeydown);
    return () => window.removeEventListener('keydown', onKeydown);
  });
</script>

{#if open}
  <!-- Backdrop: cierra al hacer click fuera -->
  <div class="fixed inset-0 z-40 bg-black/40" role="presentation" onclick={onCancel}></div>
  <div class="pointer-events-none fixed inset-0 z-50 flex items-center justify-center p-4">
    <div
      class="pointer-events-auto w-full max-w-sm rounded-xl bg-white p-6 shadow-xl"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="confirm-dialog-title"
      aria-describedby="confirm-dialog-body"
    >
      <div class="mb-5 flex items-start gap-4">
        <div
          class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full {isDanger
            ? 'bg-red-100'
            : 'bg-blue-100'}"
        >
          {#if isDanger}
            <Trash2 size={18} class="text-red-600" />
          {:else}
            <Info size={18} class="text-blue-600" />
          {/if}
        </div>
        <div>
          <h2 id="confirm-dialog-title" class="text-base font-bold text-gray-900">
            {title}
          </h2>
          <p id="confirm-dialog-body" class="mt-1 text-sm text-gray-500">{body}</p>
        </div>
      </div>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          onclick={onCancel}
          class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
        >
          {cancelLabel}
        </button>
        <button
          type="button"
          onclick={onConfirm}
          class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white transition-colors {isDanger
            ? 'bg-red-600 hover:bg-red-700'
            : 'bg-blue-600 hover:bg-blue-700'}"
        >
          {#if isDanger}
            <Trash2 size={14} />
          {:else}
            <AlertTriangle size={14} />
          {/if}
          {confirmLabel}
        </button>
      </div>
    </div>
  </div>
{/if}
