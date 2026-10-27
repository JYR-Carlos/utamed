<script lang="ts">
  /**
   * Estado vacío, de carga o de error de la guía dm (catálogo «Estados» del
   * lote 1). Los textos los pone cada vista; aquí sólo la forma.
   */
  import type { Snippet } from 'svelte';
  import { LoaderCircle } from 'lucide-svelte';

  interface Props {
    titulo: string;
    texto?: string;
    /** Ícono de lucide; no se usa mientras carga. */
    icono?: typeof LoaderCircle;
    tipo?: 'vacio' | 'cargando' | 'error';
    /** Botón o enlace bajo el texto. */
    accion?: Snippet;
    class?: string;
  }

  let { titulo, texto = '', icono, tipo = 'vacio', accion, class: clase = '' }: Props = $props();

  const esError = $derived(tipo === 'error');
</script>

<div
  role={esError ? 'alert' : tipo === 'cargando' ? 'status' : undefined}
  aria-busy={tipo === 'cargando' ? 'true' : undefined}
  class="flex flex-col items-center justify-center gap-3 rounded-xl border border-t-4 border-dm-linea bg-dm-tarjeta px-6 py-7 text-center {esError
    ? 'border-t-dm-error'
    : ''} {clase}"
>
  {#if tipo === 'cargando'}
    <div class="flex w-full max-w-90 flex-col gap-2.5" aria-hidden="true">
      {#each ['w-[45%]', 'w-[35%]'] as ancho, i (i)}
        <div class="flex items-center gap-2.5">
          <span class="size-9 shrink-0 animate-pulse rounded-full bg-dm-sutil"></span>
          <span class="flex flex-1 flex-col gap-1.5">
            <span class="h-2.5 animate-pulse rounded bg-dm-sutil {ancho}"></span>
            <span class="h-2.5 w-[80%] animate-pulse rounded bg-dm-sutil"></span>
          </span>
        </div>
      {/each}
    </div>
    <span class="inline-flex items-center gap-2 text-sm font-semibold text-dm-ink-2">
      <LoaderCircle size={16} class="animate-spin" aria-hidden="true" />{titulo}
    </span>
  {:else}
    {#if icono}
      {@const Icono = icono}
      <span
        aria-hidden="true"
        class="flex size-14 items-center justify-center rounded-[14px] bg-[length:8px_8px] {esError
          ? 'bg-dm-error-s bg-[linear-gradient(rgb(180_35_24/0.12)_1px,transparent_1px),linear-gradient(90deg,rgb(180_35_24/0.12)_1px,transparent_1px)] text-dm-error'
          : 'bg-dm-sutil bg-[linear-gradient(var(--color-dm-linea)_1px,transparent_1px),linear-gradient(90deg,var(--color-dm-linea)_1px,transparent_1px)] text-dm-ink-2'}"
      >
        <Icono size={26} />
      </span>
    {/if}
    <span class="flex max-w-105 flex-col gap-1.5">
      <span class="text-lg leading-6 font-bold text-dm-ink">{titulo}</span>
      {#if texto}<span class="text-sm leading-[21px] text-dm-ink-2">{texto}</span>{/if}
    </span>
    {@render accion?.()}
  {/if}
</div>
