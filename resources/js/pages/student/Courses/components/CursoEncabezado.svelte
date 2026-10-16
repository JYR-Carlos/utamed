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
   * La barra copia la navegación de la UTAMED anterior, a la que los alumnos
   * están habituados: botones del mismo ancho en una fila —Programa,
   * Actividades, Resumen de notas, Biblioteca— con el activo relleno. Los
   * datos de la asignatura (créditos, horas) ya no se repiten aquí: están en
   * la pestaña Programa. Mensajería y Bitácora son páginas propias, así que
   * van como enlaces al final de la misma fila.
   */
  import { Link } from '@inertiajs/svelte';
  import { BarChart3, BookMarked, ClipboardList, FileText, History, MessagesSquare } from 'lucide-svelte';
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

  const contexto = $derived(
    [codigo, curso?.letra_grupo ? `Grupo ${curso.letra_grupo}` : '', curso?.carrera_nombre]
      .filter(Boolean)
      .join(' · '),
  );

  const PESTANAS: Array<{ id: SeccionCurso; etiqueta: string; icono: typeof FileText }> = [
    { id: 'programa', etiqueta: 'Programa', icono: FileText },
    { id: 'actividades', etiqueta: 'Actividades', icono: ClipboardList },
    { id: 'notas', etiqueta: 'Resumen de notas', icono: BarChart3 },
    { id: 'biblioteca', etiqueta: 'Biblioteca', icono: BookMarked },
  ];

  const BASE =
    'inline-flex min-h-12 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-center text-sm font-semibold leading-tight no-underline transition-colors';
  const INACTIVA = 'bg-gray-100 text-gray-700 hover:bg-uta-blue-light hover:text-uta-blue';
  const ACTIVA = 'bg-uta-blue text-white shadow-sm';
</script>

<header class="mb-8">
  <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold leading-tight tracking-tight text-slate-900 md:text-3xl">
        {titulo}
      </h1>
      {#if contexto}
        <p class="text-sm text-slate-500">{contexto}</p>
      {/if}
    </div>

    {#if periodo}
      <span
        class="inline-flex h-fit w-fit items-center gap-1.5 rounded-full border border-uta-blue/20 bg-uta-blue-light px-3 py-2 text-xs font-bold text-uta-blue"
      >
        {periodo}
      </span>
    {/if}
  </div>

  <nav id="secciones-curso" aria-label="Secciones del curso" class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
    {#each PESTANAS as pestana (pestana.id)}
      {@const Icono = pestana.icono}
      {@const activa = seccion === pestana.id}
      <button
        type="button"
        class="{BASE} {activa ? ACTIVA : INACTIVA}"
        aria-current={activa ? 'page' : undefined}
        onclick={() => onCambiarSeccion(pestana.id)}
      >
        <Icono class="h-4 w-4 shrink-0" />
        {pestana.etiqueta}
        {#if pestana.id === 'actividades' && totalActividades > 0}
          <span class="font-medium {activa ? 'text-white/70' : 'text-gray-600'}">({totalActividades})</span>
        {/if}
      </button>
    {/each}
    <Link href={`/estudiante/cursos/${curso?.id_curso}/mensajeria`} class="{BASE} {INACTIVA}">
      <MessagesSquare class="h-4 w-4 shrink-0" />
      Mensajería
    </Link>
    <!-- Bitácora del curso (T41): todas las agendas del alumno juntas. -->
    <Link href={`/estudiante/cursos/${curso?.id_curso}/bitacora`} class="{BASE} {INACTIVA}">
      <History class="h-4 w-4 shrink-0" />
      Bitácora
    </Link>
  </nav>
</header>
