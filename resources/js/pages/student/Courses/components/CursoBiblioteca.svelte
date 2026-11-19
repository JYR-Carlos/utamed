<script lang="ts">
  /**
   * Pestaña «Biblioteca» de la ficha del curso: la sección VIII del programa
   * como lista de recursos, agrupada por sus subtítulos (bibliografía
   * obligatoria, complementaria, o por unidad).
   *
   * Con varios grupos, en escritorio una columna lateral los resume y lleva a
   * cada uno; en móvil eso mismo es una fila de chips. Con tres o menos la
   * columna no aparece: la página casi no se mueve y parecía que no hacía nada
   * (OBS-47).
   *
   * Al final, un acceso directo a la Mensajería del curso para avisar si un
   * recurso no abre, en vez de un texto que pedía ir a buscarla.
   */
  import { BookMarked, MessageSquare } from 'lucide-svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import ListaRecursos from '@/components/student/programa/ListaRecursos.svelte';
  import { btnSecundario, tarjeta } from '@/lib/dm';
  import type { GrupoRecursos } from '@/lib/programaAlumno';

  interface Props {
    grupos: GrupoRecursos[];
    /** Hay programa aprobado (cambia el texto cuando no hay recursos). */
    hayPrograma: boolean;
    /** Pestaña Mensajería del curso. */
    hrefMensajeria: string;
    onIrAMensajeria: () => void;
  }

  let { grupos, hayPrograma, hrefMensajeria, onIrAMensajeria }: Props = $props();

  const cuenta = (n: number) => `${n} ${n === 1 ? 'recurso' : 'recursos'}`;
  const conPanel = $derived(grupos.length > 3);
</script>

{#if grupos.length === 0}
  <EstadoVacio
    icono={BookMarked}
    titulo={hayPrograma ? 'Sin recursos por ahora' : 'Todavía no hay bibliografía'}
    texto={hayPrograma
      ? 'El programa de este curso no registra bibliografía.'
      : 'La bibliografía se publica con el programa del curso, que aún no ha sido aprobado.'}
  />
{:else}
  {#if grupos.length > 1}<nav
    aria-label="Grupos de la biblioteca"
    class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] xl:hidden [&::-webkit-scrollbar]:hidden"
  >
    {#each grupos as g (g.id)}
      <a
        href="#{g.id}"
        class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full border border-dm-control bg-dm-tarjeta px-3.5 py-1.5 text-sm font-semibold whitespace-nowrap text-dm-ink no-underline hover:bg-dm-sutil"
      >
        {g.corto}<span class="rounded-full bg-dm-sutil px-1.5 text-[13px] leading-[18px] font-bold text-dm-ink-2">{g.recursos.length}</span>
      </a>
    {/each}
  </nav>{/if}

  <div class="flex flex-col gap-6 {conPanel ? 'xl:grid xl:grid-cols-[minmax(0,1fr)_280px] xl:items-start' : ''}">
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

      <div class="{tarjeta} flex flex-wrap items-center justify-between gap-3 px-4 py-3.5 lg:px-6">
        <p class="m-0 text-[15px] leading-[22px] text-dm-ink-2">¿Un enlace o un archivo no abre? Avísale a tu docente.</p>
        <a
          href={hrefMensajeria}
          onclick={(e) => {
            e.preventDefault();
            onIrAMensajeria();
          }}
          class="{btnSecundario} min-h-10 whitespace-nowrap"
        >
          <MessageSquare size={16} aria-hidden="true" />Escribir a mi docente
        </a>
      </div>
    </div>

    {#if conPanel}<aside aria-labelledby="t-resumen-biblioteca" class="sticky top-4 hidden overflow-hidden xl:block {tarjeta}">
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
    </aside>{/if}
  </div>
{/if}

