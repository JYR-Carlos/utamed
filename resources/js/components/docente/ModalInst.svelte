<script lang="ts">
  /**
   * Modal de la dirección institucional UTA: velo, tarjeta con cabecera
   * (título y subtítulo), cuerpo con desplazamiento propio y pie de acciones.
   * Escape y el clic en el velo cierran.
   */
  import type { Snippet } from 'svelte';
  import { X } from 'lucide-svelte';
  import { btnFantasma, titulo as claseTitulo } from '@/lib/inst';

  interface Props {
    titulo: string;
    subtitulo?: string;
    /** Ancho máximo en px. */
    ancho?: number;
    onCerrar: () => void;
    children?: Snippet;
    pie?: Snippet;
  }

  let { titulo, subtitulo, ancho = 560, onCerrar, children, pie }: Props = $props();

  const id = `modal-${Math.random().toString(36).slice(2, 8)}`;
</script>

<svelte:window onkeydown={(e) => e.key === 'Escape' && onCerrar()} />

<div
  class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-inst-velo p-3 sm:items-center sm:p-6"
  role="presentation"
  onclick={(e) => e.target === e.currentTarget && onCerrar()}
>
  <div
    role="dialog"
    aria-modal="true"
    aria-labelledby={id}
    class="flex max-h-[calc(100dvh-24px)] w-full flex-col overflow-hidden rounded-lg border border-inst-linea bg-inst-tarjeta text-inst-ink shadow-[0_18px_48px_rgba(16,24,40,.28)] sm:max-h-[calc(100dvh-48px)]"
    style="max-width: {ancho}px"
  >
    <div class="flex items-start gap-3 border-b border-inst-linea px-5 py-4">
      <div class="flex min-w-0 flex-1 flex-col gap-0.5">
        <h2 {id} class="{claseTitulo} m-0 text-[21px] leading-[26px]">{titulo}</h2>
        {#if subtitulo}<span class="text-inst-ink-2">{subtitulo}</span>{/if}
      </div>
      <button type="button" class="{btnFantasma} size-11 px-0! lg:size-10" aria-label="Cerrar" onclick={onCerrar}>
        <X size={20} aria-hidden="true" />
      </button>
    </div>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-5 py-4">
      {@render children?.()}
    </div>
    {#if pie}
      <div class="flex flex-wrap items-center gap-2 border-t border-inst-linea bg-inst-sutil px-5 py-3.5">
        {@render pie()}
      </div>
    {/if}
  </div>
</div>
