<script lang="ts">
  import type { Snippet } from 'svelte';

  type ToastType = 'success' | 'error' | 'warning' | 'info';
  type ToastVariant = 'pill' | 'card';

  interface Props {
    type?: ToastType;
    message?: string | null;
    variant?: ToastVariant;
    duration?: number;
    onClose?: () => void;
    children?: Snippet;
  }

  let {
    type = 'success',
    message = null,
    variant = 'pill',
    duration = 5000,
    onClose,
    children,
  }: Props = $props();

  $effect(() => {
    if (!message || duration <= 0 || !onClose) return;

    const timer = setTimeout(() => {
      onClose();
    }, duration);

    return () => clearTimeout(timer);
  });
</script>

{#if message || children}
  <div
    role="status"
    aria-live="polite"
    class={[
      'flex items-center gap-3 px-4 py-3 border text-sm font-medium transition-all',
      variant === 'pill'
        ? 'toast-slide fixed bottom-6 right-6 z-[9999] max-w-sm sm:max-w-md rounded-xl shadow-xl'
        : 'w-full rounded-lg mb-4 shadow-sm',
      type === 'success' && 'bg-emerald-50 border-emerald-200 text-emerald-800',
      type === 'error' && 'bg-rose-50 border-rose-200 text-rose-800',
      type === 'warning' && 'bg-amber-50 border-amber-200 text-amber-800',
      type === 'info' && 'bg-blue-50 border-blue-200 text-blue-800',
    ].filter(Boolean).join(' ')}
  >
    {#if type === 'success'}
      <svg class="w-5 h-5 shrink-0 text-emerald-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
      </svg>
    {:else if type === 'error'}
      <svg class="w-5 h-5 shrink-0 text-rose-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" />
        <line x1="15" y1="9" x2="9" y2="15" />
        <line x1="9" y1="9" x2="15" y2="15" />
      </svg>
    {:else if type === 'warning'}
      <svg class="w-5 h-5 shrink-0 text-amber-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
        <line x1="12" y1="9" x2="12" y2="13" />
        <line x1="12" y1="17" x2="12.01" y2="17" />
      </svg>
    {:else}
      <svg class="w-5 h-5 shrink-0 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="16" x2="12" y2="12" />
        <line x1="12" y1="8" x2="12.01" y2="8" />
      </svg>
    {/if}

    <div class="flex-1 min-w-0">
      {#if message}
        <span>{message}</span>
      {/if}
      {@render children?.()}
    </div>

    {#if onClose}
      <button
        type="button"
        onclick={onClose}
        class="shrink-0 p-1 -mr-1 rounded-md opacity-60 hover:opacity-100 hover:bg-black/5 transition cursor-pointer"
        aria-label="Cerrar notificación"
      >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    {/if}
  </div>
{/if}

<style>
  @keyframes toast-slide-in {
    from {
      opacity: 0;
      transform: translateY(0.75rem);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .toast-slide {
    animation: toast-slide-in 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  }
</style>
