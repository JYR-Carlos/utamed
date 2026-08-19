<script lang="ts">
  /**
   * Modal de confirmación — base única del panel de administración.
   *
   * Antes convivían tres bases (ésta, custom/admin/DeleteConfirmation y
   * el diálogo propio de Cursos) y ninguna salvo la de Cursos decía QUÉ
   * registro se iba a destruir: el usuario leía «¿Eliminar Estudiante?»
   * sobre una fila que ya no tenía a la vista.
   *
   * De ahí las dos piezas centrales:
   * - `recordName` / `recordMeta` identifican el registro afectado.
   * - `confirmPhrase` obliga a escribirlo cuando la acción es
   *   irreversible, para que no se confirme por inercia.
   */
  import type { Snippet } from 'svelte';

  interface Props {
    isOpen: boolean;
    /** Qué se va a hacer. Ej: «Eliminar estudiante». */
    title: string;
    /** Registro afectado, destacado bajo el título. */
    recordName?: string | null;
    /** Datos que desambiguan el registro: RUT, código, carrera… */
    recordMeta?: string[];
    /** Consecuencia de la acción, en una frase. */
    message?: string;
    /**
     * Escribir esto habilita el botón de confirmar. Reservado para lo
     * irreversible; para un soft delete reversible sobra.
     */
    confirmPhrase?: string | null;
    tone?: 'danger' | 'warning' | 'info';
    confirmLabel?: string;
    cancelLabel?: string;
    isLoading?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
    /** Detalle extra: impacto, advertencias, lista de dependencias. */
    children?: Snippet;
  }

  let {
    isOpen = $bindable(),
    title,
    recordName = null,
    recordMeta = [],
    message,
    confirmPhrase = null,
    tone = 'warning',
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    isLoading = false,
    onConfirm,
    onCancel,
    children,
  }: Props = $props();

  const toneConfig = {
    danger: {
      wrap: 'bg-[var(--action-danger-soft)] text-[var(--action-danger)]',
      button: 'btn-danger',
      path: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
    },
    warning: {
      wrap: 'bg-[var(--state-warn-soft)] text-[var(--state-warn)]',
      button: 'btn-primary',
      path: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
    },
    info: {
      wrap: 'bg-[var(--state-info-soft)] text-[var(--state-info)]',
      button: 'btn-primary',
      path: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
    },
  };

  const config = $derived(toneConfig[tone]);

  let typed = $state('');

  // Al cerrarse se limpia, para que reabrir no herede lo ya escrito.
  $effect(() => {
    if (!isOpen) typed = '';
  });

  const needsTyping = $derived(Boolean(confirmPhrase));
  const canConfirm = $derived(
    !isLoading && (!needsTyping || typed.trim() === confirmPhrase?.trim()),
  );

  function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && !isLoading) onCancel();
  }

  function handleBackdrop(event: MouseEvent) {
    if (event.target === event.currentTarget && !isLoading) onCancel();
  }
</script>

<svelte:window onkeydown={isOpen ? handleKeydown : undefined} />

{#if isOpen}
  <div
    class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 modal-fade"
    role="presentation"
    onclick={handleBackdrop}
  >
    <div
      class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="confirm-title"
    >
      <div class="p-5 border-b border-gray-100 flex items-start gap-3">
        <div class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center {config.wrap}">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d={config.path} />
          </svg>
        </div>
        <div class="min-w-0">
          <h3 id="confirm-title" class="text-[15px] font-bold text-gray-900">{title}</h3>
          {#if recordName}
            <p class="text-sm font-semibold text-gray-900 mt-1 break-words">{recordName}</p>
          {/if}
          {#if recordMeta.length}
            <p class="text-[13px] text-gray-500 mt-0.5">
              {recordMeta.filter(Boolean).join(' · ')}
            </p>
          {/if}
        </div>
      </div>

      <div class="p-5 space-y-4">
        {#if message}
          <p class="text-[13px] text-gray-600 leading-relaxed">{message}</p>
        {/if}

        {#if children}
          {@render children()}
        {/if}

        {#if needsTyping}
          <div>
            <label for="confirm-phrase" class="block text-[13px] text-gray-600 mb-1.5">
              Escribe <strong class="font-semibold text-gray-900">{confirmPhrase}</strong> para
              confirmar
            </label>
            <input
              id="confirm-phrase"
              type="text"
              bind:value={typed}
              class="field-input"
              autocomplete="off"
              disabled={isLoading}
            />
          </div>
        {/if}
      </div>

      <div class="px-5 pb-5 flex justify-end gap-2">
        <button type="button" onclick={onCancel} disabled={isLoading} class="btn btn-neutral">
          {cancelLabel}
        </button>
        <button
          type="button"
          onclick={onConfirm}
          disabled={!canConfirm}
          class="btn {config.button}"
        >
          {#if isLoading}
            <svg
              class="w-3.5 h-3.5 animate-spin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
              ></path>
            </svg>
            Procesando…
          {:else}
            {confirmLabel}
          {/if}
        </button>
      </div>
    </div>
  </div>
{/if}

<style>
  @keyframes modal-fade-in {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }
  @keyframes slide-up {
    from {
      opacity: 0;
      transform: translateY(8px) scale(0.97);
    }
    to {
      opacity: 1;
      transform: none;
    }
  }
  .modal-fade {
    animation: modal-fade-in 0.15s ease both;
  }
  .modal-fade > * {
    animation: slide-up 0.2s cubic-bezier(0.16, 1, 0.3, 1) both;
  }
  @media (prefers-reduced-motion: reduce) {
    .modal-fade,
    .modal-fade > * {
      animation: none;
    }
  }
</style>
