<script lang="ts" module>
  /** Pestañas que se resuelven dentro de la ficha, sin cambiar de página. */
  export type SeccionCurso =
    | 'actividades'
    | 'programa'
    | 'notas'
    | 'biblioteca'
    | 'calendario'
    | 'mensajeria'
    | 'bitacora';

  export const SECCIONES_CURSO: readonly SeccionCurso[] = [
    'actividades',
    'programa',
    'notas',
    'biblioteca',
    'calendario',
    'mensajeria',
    'bitacora',
  ];
</script>

<script lang="ts">
  /**
   * Encabezado de la ficha del curso (vista del alumno): portada noche con la
   * franja del color del curso y, debajo, la fila de pestañas.
   *
   * El color llega por las variables --curso* que pone la página en un
   * contenedor (estiloCurso). En escritorio portada y pestañas forman una sola
   * tarjeta; en móvil la portada va a todo el ancho, con «‹ Mis cursos» porque
   * ahí no hay migas de pan, y la fila de pestañas se desliza hacia los lados.
   */
  import { Link } from '@inertiajs/svelte';
  import { ChevronLeft } from 'lucide-svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import { etiqueta, tituloCond } from '@/lib/dm';
  import type { Curso } from '@/types';

  interface Props {
    curso?: Curso | null;
    seccion: SeccionCurso;
    /** «Paula Fuentes · docente titular»; vacío si el curso no tiene docente. */
    docente?: string;
    totalActividades?: number;
    /** Recursos de la Biblioteca; si es 0 la pestaña no lleva contador. */
    totalRecursos?: number;
    mensajesSinLeer?: number;
    onCambiarSeccion: (seccion: SeccionCurso) => void;
  }

  let {
    curso = null,
    seccion,
    docente = '',
    totalActividades = 0,
    totalRecursos = 0,
    mensajesSinLeer = 0,
    onCambiarSeccion,
  }: Props = $props();

  const codigo = $derived(curso?.cod_asignatura || curso?.cod_curso || '');
  const titulo = $derived(
    curso?.asignatura_nombre && curso.asignatura_nombre !== 'N/A' ? curso.asignatura_nombre : curso?.nombre || 'Curso',
  );
  const grupo = $derived(curso?.letra_grupo ? `Grupo ${curso.letra_grupo}` : '');
  const ordinal = $derived(curso?.semestre_real === 1 ? '1.er' : '2.º');

  // agno_real es el año calendario del curso.
  const contexto = $derived(
    [codigo, grupo, curso?.carrera_nombre, curso?.agno_real ? `${ordinal} semestre ${curso.agno_real}` : '']
      .filter(Boolean)
      .join(' · '),
  );
  const contextoCorto = $derived(
    [codigo, grupo, curso?.agno_real ? `${ordinal} sem. ${curso.agno_real}` : ''].filter(Boolean).join(' · '),
  );

  const PESTANAS: Array<{ id: SeccionCurso; etiqueta: string }> = [
    { id: 'actividades', etiqueta: 'Actividades' },
    { id: 'programa', etiqueta: 'Programa' },
    { id: 'notas', etiqueta: 'Resumen de notas' },
    { id: 'biblioteca', etiqueta: 'Biblioteca' },
    { id: 'calendario', etiqueta: 'Calendario' },
    { id: 'mensajeria', etiqueta: 'Mensajería' },
    // Bitácora del curso (T41): todas las agendas del alumno juntas.
    { id: 'bitacora', etiqueta: 'Bitácora' },
  ];

  function cuenta(id: SeccionCurso): { texto: string; aviso: boolean } | null {
    if (id === 'actividades' && totalActividades > 0) return { texto: String(totalActividades), aviso: false };
    if (id === 'biblioteca' && totalRecursos > 0) return { texto: String(totalRecursos), aviso: false };
    if (id === 'mensajeria' && mensajesSinLeer > 0) return { texto: `${mensajesSinLeer} sin leer`, aviso: true };
    return null;
  }

  // ─── Fila deslizable ────────────────────────────────────────────────────────
  let fila = $state<HTMLElement | null>(null);
  let hayMas = $state(false);

  function medir() {
    const el = fila;
    if (!el) return;
    hayMas = el.scrollWidth - el.clientWidth - el.scrollLeft > 1;
  }

  $effect(() => {
    const el = fila;
    if (!el) return;
    medir();
    const ro = new ResizeObserver(medir);
    ro.observe(el);
    return () => ro.disconnect();
  });

  // La pestaña activa queda a la vista cuando la fila se desliza.
  $effect(() => {
    seccion;
    fila
      ?.querySelector<HTMLElement>('[aria-current="page"]')
      ?.scrollIntoView({ inline: 'nearest', block: 'nearest' });
  });
</script>

<header class="flex flex-col overflow-hidden bg-dm-tarjeta lg:rounded-xl lg:border lg:border-dm-linea">
  <section aria-labelledby="t-curso" class="sobre-noche relative overflow-hidden border-b-6 border-(--curso) bg-dm-noche">
    <Reticula class="left-[45%] lg:left-1/2" />
    <div class="relative flex flex-col gap-2 px-4 pt-3 pb-6 lg:px-10 lg:py-8">
      <Link
        href="/estudiante/cursos"
        class="inline-flex min-h-11 items-center gap-1 self-start text-sm font-semibold text-dm-noche-texto-2 no-underline hover:text-dm-noche-texto lg:hidden"
      >
        <ChevronLeft size={18} aria-hidden="true" />Mis cursos
      </Link>
      <span class="{etiqueta} text-dm-amarillo">
        <span class="lg:hidden">{contextoCorto}</span><span class="max-lg:hidden">{contexto}</span>
      </span>
      <h1 id="t-curso" class="{tituloCond} text-[34px] leading-[34px] text-dm-noche-texto lg:text-5xl lg:leading-12">
        {titulo}
      </h1>
      {#if docente}
        <span class="text-sm leading-5 text-dm-noche-texto-2 lg:text-[15px] lg:leading-[22px]">{docente}</span>
      {/if}
    </div>
  </section>

  <div class="relative border-b border-dm-linea lg:border-b-0">
    <nav
      id="secciones-curso"
      aria-label="Secciones del curso"
      bind:this={fila}
      onscroll={medir}
      class="flex gap-1 overflow-x-auto px-2 [scrollbar-width:none] lg:px-6 [&::-webkit-scrollbar]:hidden"
    >
      {#each PESTANAS as pestana (pestana.id)}
        {@const activa = seccion === pestana.id}
        {@const n = cuenta(pestana.id)}
        <button
          type="button"
          aria-current={activa ? 'page' : undefined}
          onclick={() => onCambiarSeccion(pestana.id)}
          class="inline-flex min-h-12 shrink-0 cursor-pointer items-center gap-1.5 border-b-2 px-2.5 text-sm whitespace-nowrap transition-colors lg:px-3 {activa
            ? 'border-dm-accion font-bold text-dm-ink'
            : 'border-transparent font-medium text-dm-ink-2 hover:text-dm-ink'}"
        >
          {pestana.etiqueta}
          {#if n}
            <span
              class="rounded-full px-1.5 text-[13px] leading-[18px] font-bold {n.aviso
                ? 'bg-dm-accion-suave text-dm-accion-texto'
                : 'bg-dm-sutil text-dm-ink-2'}">{n.texto}</span
            >
          {/if}
        </button>
      {/each}
    </nav>

    {#if hayMas}
      <div
        class="pointer-events-none absolute inset-y-0 right-0 w-12 bg-linear-to-r from-transparent to-dm-tarjeta"
        aria-hidden="true"
      ></div>
    {/if}
  </div>
</header>
