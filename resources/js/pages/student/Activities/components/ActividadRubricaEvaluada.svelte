<script lang="ts">
  /**
   * La rúbrica con el nivel que asignó el docente en cada criterio. En
   * escritorio, una fila por criterio con todos los niveles y el obtenido
   * marcado; en el móvil, una barra que se llena hasta el nivel obtenido.
   * La descripción de cada nivel está en la rúbrica completa (modal).
   */
  import { ChevronRight } from 'lucide-svelte';
  import type { CriterioAlumno } from '@/lib/rubricaAlumno';
  import { tarjeta } from '@/lib/dm';

  interface Props {
    criterios: CriterioAlumno[];
    onVerCompleta: () => void;
  }

  let { criterios, onVerCompleta }: Props = $props();

  const COLUMNAS = 'lg:grid lg:grid-cols-[minmax(0,1fr)_64px_360px] lg:items-center lg:gap-4';
</script>

<section aria-labelledby="t-rubrica-evaluada" class="{tarjeta} overflow-hidden">
  <div class="flex flex-col gap-0.5 border-b border-dm-linea px-4 py-3.5 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between lg:gap-3 lg:px-6 lg:py-4">
    <h2 id="t-rubrica-evaluada" class="text-lg leading-6 font-semibold">Rúbrica evaluada</h2>
    <span class="text-[13px] leading-[18px] text-dm-ink-3">Nivel que asignó tu docente en cada criterio</span>
  </div>

  <div
    aria-hidden="true"
    class="hidden border-b border-dm-linea bg-dm-sutil px-6 py-2.5 text-xs leading-4 font-bold tracking-[0.06em] text-dm-ink-3 uppercase {COLUMNAS}"
  >
    <span>Criterio</span><span>Peso</span><span>Nivel obtenido</span>
  </div>

  <ul>
    {#each criterios as c (c.id)}
      <li class="flex flex-col gap-2 border-b border-dm-linea px-4 py-3.5 lg:min-h-15 lg:px-6 lg:py-2.5 {COLUMNAS}">
        <div class="flex items-baseline justify-between gap-3 lg:contents">
          <span class="text-[15px] leading-5 font-semibold">{c.nombre}</span>
          <span class="shrink-0 text-[13px] text-dm-ink-3 tabular-nums lg:text-sm lg:text-dm-ink-2">
            <span class="sr-only">Peso{' '}</span>{c.peso}
          </span>
        </div>

        <!-- Móvil: barra hasta el nivel obtenido y su nombre. -->
        <div class="flex items-center gap-2.5 lg:hidden">
          <span aria-hidden="true" class="flex flex-1 gap-0.75">
            {#each c.barra as lleno, i (i)}
              <span class="h-2 flex-1 rounded-full {lleno ? 'bg-dm-amarillo' : 'bg-dm-linea'}"></span>
            {/each}
          </span>
          <span
            class="shrink-0 rounded-full border px-2 py-0.5 text-[13px] leading-[18px] font-bold {c.obtenido
              ? 'border-dm-amarillo bg-dm-activo-suave text-dm-activo-texto'
              : 'border-dm-linea text-dm-ink-3'}"
          >
            {c.obtenido ?? 'Sin nivel'}
          </span>
        </div>

        <!-- Escritorio: todos los niveles, el obtenido marcado. -->
        <span class="hidden gap-1 lg:flex">
          {#each c.niveles as n, i (i)}
            <span
              aria-current={n.marcado ? 'true' : undefined}
              class="inline-flex min-h-8 flex-1 items-center justify-center rounded-lg border px-1 text-center text-xs leading-4 {n.marcado
                ? 'border-dm-amarillo bg-dm-activo-suave font-bold text-dm-activo-texto'
                : 'border-dm-linea font-medium text-dm-ink-3'}"
            >
              {n.nombre}
            </span>
          {/each}
        </span>
      </li>
    {/each}
  </ul>

  <button
    type="button"
    class="flex min-h-12 w-full cursor-pointer items-center gap-1 px-4 text-sm font-semibold text-dm-enlace hover:text-dm-enlace-hover hover:underline lg:px-6"
    onclick={onVerCompleta}
  >
    Ver descripción de cada nivel<ChevronRight size={16} aria-hidden="true" />
  </button>
</section>
