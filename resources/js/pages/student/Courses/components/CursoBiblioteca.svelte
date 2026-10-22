<script lang="ts">
  /**
   * Pestaña «Biblioteca» de la ficha del curso: la sección VIII del programa
   * como lista de recursos, agrupada por sus subtítulos (bibliografía
   * obligatoria, complementaria, o por unidad).
   *
   * En escritorio una columna lateral resume los grupos y lleva a cada uno; en
   * móvil eso mismo es una fila de chips.
   */
  import { BookMarked } from 'lucide-svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import ListaRecursos from '@/components/student/programa/ListaRecursos.svelte';
  import { tarjeta } from '@/lib/dm';
  import type { GrupoRecursos } from '@/lib/programaAlumno';

  interface Props {
    grupos: GrupoRecursos[];
    /** Hay programa aprobado (cambia el texto cuando no hay recursos). */
    hayPrograma: boolean;
  }

  let { grupos, hayPrograma }: Props = $props();

  const cuenta = (n: number) => `${n} ${n === 1 ? 'recurso' : 'recursos'}`;
</script>

{#if grupos.length === 0}
  <EstadoVacio
    icono={BookMarked}
    titulo="Sin recursos por ahora"
    texto={hayPrograma
      ? 'El programa de este curso no registra bibliografía.'
      : 'La bibliografía se publica con el programa del curso, que aún no ha sido aprobado.'}
  />
{:else}
  <nav
    aria-label="Grupos de la biblioteca"
    class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] xl:hidden [&::-webkit-scrollbar]:hidden"
  >
    {#each grupos as g (g.id)}
      <a
        href="#{g.id}"
        class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full border border-dm-control bg-dm-tarjeta px-3.5 py-1.5 text-sm font-semibold whitespace-nowrap text-dm-ink no-underline hover:bg-dm-sutil"
      >
        {g.corto}<span class="rounded-full bg-dm-sutil px-1.5 text-xs leading-[18px] font-bold text-dm-ink-2">{g.recursos.length}</span>
      </a>
    {/each}
  </nav>

  <div class="flex flex-col gap-6 xl:grid xl:grid-cols-[minmax(0,1fr)_280px] xl:items-start">
    <div class="flex min-w-0 flex-col gap-4">
      {#each grupos as g (g.id)}
        <section id={g.id} aria-labelledby="t-{g.id}" class="scroll-mt-4 overflow-hidden {tarjeta}">
          <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 border-b border-dm-linea px-4 py-3.5 lg:px-6">
            <h3 id="t-{g.id}" class="font-dm-cond text-[22px] leading-[26px] font-extrabold uppercase">{g.titulo}</h3>
            <span class="text-[13px] leading-[18px] text-dm-ink-3">{cuenta(g.recursos.length)}</span>
          </div>
          <ListaRecursos recursos={g.recursos} />
        </section>
      {/each}
    </div>

    <aside aria-labelledby="t-resumen-biblioteca" class="sticky top-4 hidden overflow-hidden xl:block {tarjeta}">
      <h3 id="t-resumen-biblioteca" class="border-b border-dm-linea px-5 py-3.5 text-base leading-[22px] font-bold">
        En esta biblioteca
      </h3>
      <ul>
        {#each grupos as g (g.id)}
          <li class="border-b border-dm-linea">
            <a
              href="#{g.id}"
              class="flex min-h-11 items-center justify-between gap-3 px-5 py-2.5 text-sm font-medium text-dm-ink no-underline hover:bg-dm-sutil"
            >
              {g.titulo}<span class="shrink-0 rounded-full bg-dm-sutil px-2 text-[13px] leading-[18px] font-bold text-dm-ink-2"
                >{g.recursos.length}</span
              >
            </a>
          </li>
        {/each}
      </ul>
      <p class="px-5 pt-3 pb-4 text-[13px] leading-[18px] text-dm-ink-2">
        Sale de la sección VIII del programa. Si un enlace no abre, avísale a tu docente por Mensajería.
      </p>
    </aside>
  </div>
{/if}

