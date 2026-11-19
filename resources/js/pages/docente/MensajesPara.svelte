<script lang="ts">
  /**
   * «Mensajes para» — /docente/mensajeria (revisión «Mi UTAmed»).
   *
   * Los cursos del semestre vigente; cada uno abre directo su mensajería (la
   * pestaña Mensajes del curso, curso.mensaje). Sólo dice cuántos mensajes
   * hay sin leer: sin cantidad de estudiantes ni pendientes por revisar.
   * La bandeja de mensajes de las actividades (agenda) queda enlazada al pie.
   */
  import { Link } from '@inertiajs/svelte';
  import { ChevronRight, MessageSquare } from 'lucide-svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { nombreSemestre } from '@/lib/cursoDocente';
  import { chip, codigo, cuerpo, tarjeta, titulo, tono } from '@/lib/inst';
  import type { BreadcrumbItem } from '@/types';

  interface CursoMensajes {
    id_curso: number;
    nombre: string;
    codigo: string;
    letra_grupo: string | null;
    no_leidos: number;
  }

  interface Props {
    periodo: { sem: number; ano: number } | null;
    cursos: CursoMensajes[];
  }

  let { periodo, cursos }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Mensajes para', href: '/docente/mensajeria' },
  ];
</script>

<svelte:head>
  <title>Mensajes para | UTAmed</title>
</svelte:head>

<DocenteLayout {breadcrumbs} ceja={periodo ? nombreSemestre(periodo.sem, periodo.ano) : undefined} titulo="Mensajes para">
  <div class="{cuerpo} max-w-[920px]">
    {#if cursos.length > 0}
      <p class="m-0 text-inst-ink-2">Elige el curso para abrir su mensajería.</p>
      <ul class="{tarjeta} m-0 list-none overflow-hidden p-0">
        {#each cursos as c (c.id_curso)}
          <li class="border-b border-inst-linea last:border-b-0">
            <Link
              href="/docente/cursos/{c.id_curso}/mensajeria"
              class="flex min-h-18 items-center gap-4 px-5 py-3 text-inst-ink no-underline hover:bg-inst-sutil hover:text-inst-ink"
            >
              <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-inst-accion-s text-inst-accion-t">
                <MessageSquare size={20} />
              </span>
              <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                <span class={codigo}>{c.letra_grupo ? `${c.codigo} · Grupo ${c.letra_grupo}` : c.codigo}</span>
                <span class="{titulo} text-[17px] leading-[22px]">{c.nombre}</span>
              </span>
              {#if c.no_leidos > 0}
                <span class="{chip} {tono.solido} shrink-0">{c.no_leidos} sin leer</span>
              {:else}
                <span class="shrink-0 text-[13px] text-inst-ink-3">Sin mensajes nuevos</span>
              {/if}
              <ChevronRight size={18} aria-hidden="true" class="shrink-0 text-inst-ink-3" />
            </Link>
          </li>
        {/each}
      </ul>
    {:else}
      <section class="{tarjeta} px-6 py-10 text-center">
        <p class="m-0 font-bold">No tienes cursos este semestre</p>
        <p class="m-0 mt-1 text-inst-ink-2">Cuando tengas cursos asignados, su mensajería aparecerá aquí.</p>
      </section>
    {/if}

    <Link href="/docente/mensajes" class="inline-flex min-h-11 items-center gap-1.5 self-start font-bold text-inst-enlace no-underline hover:underline">
      Mensajes de las actividades
      <ChevronRight size={16} aria-hidden="true" />
    </Link>
  </div>
</DocenteLayout>
