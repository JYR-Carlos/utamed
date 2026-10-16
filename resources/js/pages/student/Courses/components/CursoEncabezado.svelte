<script lang="ts" module>
  /** Pestañas que se resuelven dentro de la ficha, sin cambiar de página. */
  export type SeccionCurso = 'programa' | 'actividades' | 'notas' | 'biblioteca';

  export const SECCIONES_CURSO: readonly SeccionCurso[] = ['programa', 'actividades', 'notas', 'biblioteca'];
</script>

<script lang="ts">
  /**
   * Encabezado de la ficha del curso (vista del alumno): título y barra de
   * pestañas.
   *
   * La barra es una bandeja de una sola fila —Programa, Actividades, Resumen
   * de notas, Biblioteca— con la pestaña activa rellena. En pantallas
   * angostas la fila se desliza hacia los lados y un degradado en el borde
   * derecho avisa de que hay más pestañas; en escritorio caben todas. Los
   * datos de la asignatura (créditos, horas) no se repiten aquí: están en la
   * pestaña Programa. Mensajería y Bitácora son páginas propias, así que van
   * como enlaces al final de la misma fila.
   */
  import { Link } from '@inertiajs/svelte';
  import type { Curso } from '@/types';

  interface Props {
    curso?: Curso | null;
    seccion: SeccionCurso;
    totalActividades?: number;
    onCambiarSeccion: (seccion: SeccionCurso) => void;
  }

  let { curso = null, seccion, totalActividades = 0, onCambiarSeccion }: Props = $props();

  const codigo = $derived(curso?.cod_asignatura || curso?.cod_curso || '');
  const titulo = $derived(curso?.asignatura_nombre || curso?.nombre || 'Curso');

  // agno_real/semestre_real son el año y semestre de la malla (1..n), no el año
  // calendario: se rotulan igual que en el resto de la app.
  const periodo = $derived(
    curso?.agno_real && curso?.semestre_real
      ? `Año ${curso.agno_real} · Semestre ${curso.semestre_real}`
      : '',
  );

  // Una sola línea sobre el título: código, grupo, carrera y período.
  const contexto = $derived(
    [codigo, curso?.letra_grupo ? `Grupo ${curso.letra_grupo}` : '', curso?.carrera_nombre, periodo]
      .filter(Boolean)
      .join(' · '),
  );

  const PESTANAS: Array<{ id: SeccionCurso; etiqueta: string }> = [
    { id: 'programa', etiqueta: 'Programa' },
    { id: 'actividades', etiqueta: 'Actividades' },
    { id: 'notas', etiqueta: 'Resumen de notas' },
    { id: 'biblioteca', etiqueta: 'Biblioteca' },
  ];

  const BASE =
    'inline-flex min-h-12 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-4 text-sm no-underline transition-colors';
  const INACTIVA = 'font-medium text-slate-700 hover:bg-white hover:text-uta-blue';
  const ACTIVA = 'bg-uta-blue font-semibold text-white';

  // ─── Bandeja deslizable ─────────────────────────────────────────────────────
  let bandeja = $state<HTMLElement | null>(null);
  let hayMas = $state(false);

  function medir() {
    const el = bandeja;
    if (!el) return;
    hayMas = el.scrollWidth - el.clientWidth - el.scrollLeft > 1;
  }

  $effect(() => {
    const el = bandeja;
    if (!el) return;
    medir();
    const ro = new ResizeObserver(medir);
    ro.observe(el);
    return () => ro.disconnect();
  });

  // La pestaña activa queda a la vista cuando la fila se desliza.
  $effect(() => {
    seccion;
    bandeja
      ?.querySelector<HTMLElement>('[aria-current="page"]')
      ?.scrollIntoView({ inline: 'nearest', block: 'nearest' });
  });
</script>

<header class="mb-8">
  <div class="mb-6 flex flex-col gap-1">
    {#if contexto}
      <p class="text-xs font-medium text-slate-600">{contexto}</p>
    {/if}
    <h1 class="text-2xl font-extrabold leading-tight tracking-tight text-slate-900 md:text-3xl">
      {titulo}
    </h1>
  </div>

  <div class="relative md:w-fit">
    <nav
      id="secciones-curso"
      aria-label="Secciones del curso"
      bind:this={bandeja}
      onscroll={medir}
      class="flex gap-1 overflow-x-auto rounded-xl bg-gray-100 p-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
      {#each PESTANAS as pestana (pestana.id)}
        {@const activa = seccion === pestana.id}
        <button
          type="button"
          class="{BASE} {activa ? ACTIVA : INACTIVA}"
          aria-current={activa ? 'page' : undefined}
          onclick={() => onCambiarSeccion(pestana.id)}
        >
          {pestana.etiqueta}
          {#if pestana.id === 'actividades' && totalActividades > 0}
            <span class="font-medium {activa ? '' : 'text-gray-600'}">({totalActividades})</span>
          {/if}
        </button>
      {/each}
      <Link href={`/estudiante/cursos/${curso?.id_curso}/mensajeria`} class="{BASE} {INACTIVA}">
        Mensajería
      </Link>
      <!-- Bitácora del curso (T41): todas las agendas del alumno juntas. -->
      <Link href={`/estudiante/cursos/${curso?.id_curso}/bitacora`} class="{BASE} {INACTIVA}">
        Bitácora
      </Link>
    </nav>

    {#if hayMas}
      <div
        class="pointer-events-none absolute inset-y-0 right-0 w-12 rounded-r-xl bg-gradient-to-r from-transparent to-gray-100"
        aria-hidden="true"
      ></div>
    {/if}
  </div>
</header>
