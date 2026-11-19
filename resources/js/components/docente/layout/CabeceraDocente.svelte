<script lang="ts">
  /**
   * Cabecera de página del docente: ruta, rótulo opcional, título con la raya
   * de oro y, a la derecha, las acciones o el dato de la página.
   * La arma DocenteLayout cuando la página le pasa `titulo`. Con `pestanas`
   * (las secciones del curso) queda fija arriba al desplazar la página.
   */
  import { Link } from '@inertiajs/svelte';
  import { ChevronRight } from 'lucide-svelte';
  import type { Snippet } from 'svelte';
  import type { BreadcrumbItem } from '@/types';

  interface Props {
    breadcrumbs?: BreadcrumbItem[];
    ceja?: string;
    titulo?: string;
    acciones?: Snippet;
    detalle?: Snippet;
    pestanas?: Snippet;
  }

  let { breadcrumbs = [], ceja, titulo, acciones, detalle, pestanas }: Props = $props();
</script>

<div class="border-b border-inst-linea bg-inst-tarjeta {pestanas ? 'sticky top-0 z-30' : ''}">
  <div
    class="mx-auto flex w-full max-w-[1200px] flex-wrap items-end justify-between gap-4 px-4 pt-3.5 pb-4.5 lg:px-10 lg:pt-4.5 lg:pb-5.5"
  >
    <div class="flex min-w-0 flex-col gap-1.5">
      {#if breadcrumbs.length > 1}
        <nav aria-label="Ruta">
          <ol class="flex flex-wrap items-center gap-1 text-[13px] leading-[18px] text-inst-ink-2">
            {#each breadcrumbs as miga, i (i)}
              {#if i === breadcrumbs.length - 1}
                <li aria-current="page" class="min-w-0 truncate">{miga.title}</li>
              {:else}
                <li class="flex items-center gap-1">
                  <Link href={miga.href} class="font-bold text-inst-enlace no-underline hover:text-inst-accion-h hover:underline">
                    {miga.title}
                  </Link>
                  <ChevronRight size={14} aria-hidden="true" class="text-inst-ink-3" />
                </li>
              {/if}
            {/each}
          </ol>
        </nav>
      {/if}
      {#if titulo}
        {#if ceja}
          <span class="text-[13px] leading-[18px] font-bold tracking-[0.06em] text-inst-enlace uppercase">{ceja}</span>
        {/if}
        <h1 class="m-0 font-inst-titulo text-2xl leading-[30px] font-bold lg:text-[30px] lg:leading-9">{titulo}</h1>
        {#if !pestanas}<span aria-hidden="true" class="block h-1 w-12 bg-inst-oro"></span>{/if}
        {@render detalle?.()}
      {/if}
    </div>
    {@render acciones?.()}
  </div>
  {#if pestanas}
    <div class="mx-auto -mt-2 w-full max-w-[1200px] px-1 lg:px-6">
      {@render pestanas()}
    </div>
  {/if}
</div>
