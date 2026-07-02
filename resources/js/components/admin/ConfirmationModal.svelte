<script lang="ts">
  /**
   * Modal genérico de confirmación reutilizable.
   * Aceptar y cancel callbacks, loading state, e icono customizable.
   */
  import type { Snippet } from 'svelte';

  interface Props {
    isOpen: boolean;
    title: string;
    message?: string;
    icon?: 'warning' | 'info' | 'error' | 'success';
    confirmLabel?: string;
    cancelLabel?: string;
    isLoading?: boolean;
    isDangerous?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
    children?: Snippet;
  }

  let {
    isOpen = $bindable(),
    title,
    message,
    icon = 'warning',
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    isLoading = false,
    isDangerous = false,
    onConfirm,
    onCancel,
    children,
  }: Props = $props();

  const iconConfig = {
    warning: {
      bg: 'bg-amber-50',
      border: 'border-amber-100',
      text: 'text-amber-600',
      icon: 'M12 9v2m0 4v2m7.07-7.07a9 9 0 11-12.14 0',
    },
    info: {
      bg: 'bg-blue-50',
      border: 'border-blue-100',
      text: 'text-blue-600',
      icon: 'M12 9v2m0 4v2m7.07-7.07a9 9 0 11-12.14 0',
    },
    error: {
      bg: 'bg-red-50',
      border: 'border-red-100',
      text: 'text-red-600',
      icon: 'M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    success: {
      bg: 'bg-green-50',
      border: 'border-green-100',
      text: 'text-green-600',
      icon: 'M5 13l4 4L19 7',
    },
  };

  const config = $derived(iconConfig[icon]);
</script>

{#if isOpen}
  <div
    class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 modal-fade"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-title"
  >
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
      <!-- Header -->
      <div class="p-5 border-b border-gray-100 flex items-start gap-3">
        <div
          class="shrink-0 w-10 h-10 rounded-lg {config.bg} border {config.border} flex items-center justify-center {config.text}"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d={config.icon} />
          </svg>
        </div>
        <div>
          <h3 id="modal-title" class="text-[15px] font-bold text-gray-900">{title}</h3>
          {#if message}
            <p class="text-sm text-gray-500 mt-0.5">{message}</p>
          {/if}
        </div>
      </div>

      <!-- Content -->
      <div class="p-5">
        {#if children}
          {@render children()}
        {:else if message}
          <p class="text-[13px] text-gray-600 leading-relaxed">{message}</p>
        {/if}
      </div>

      <!-- Actions -->
      <div class="px-5 pb-5 flex justify-end gap-2">
        <button
          type="button"
          onclick={onCancel}
          disabled={isLoading}
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition disabled:opacity-50 cursor-pointer"
        >
          {cancelLabel}
        </button>
        <button
          type="button"
          onclick={onConfirm}
          disabled={isLoading}
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg transition disabled:opacity-50 cursor-pointer {isDangerous
            ? 'bg-amber-700 hover:bg-amber-800'
            : 'bg-blue-700 hover:bg-blue-800'}"
        >
          {#if isLoading}
            <svg
              class="w-3.5 h-3.5 animate-spin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
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
</style>
