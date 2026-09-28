<script lang="ts">
  /**
   * Encabezado de la ficha del curso (vista del alumno).
   *
   * Identifica el curso antes de la lista de actividades: los alumnos entraban
   * y veían las entregas sin saber de qué curso se trataba.
   *
   * Sigue el patrón del dashboard del estudiante — chip de período, título
   * extrabold, tarjeta redondeada con borde gris — y usa el azul UTA como
   * acento, no como fondo.
   */
  import { Link } from '@inertiajs/svelte';
  import { BarChart3, ClipboardList, FileText, History, MessagesSquare } from 'lucide-svelte';
  import type { Curso } from '@/types';

  interface Props {
    curso?: Curso | null;
    /** Sin programa publicado el botón no lleva a ninguna parte, así que no se muestra. */
    tienePrograma?: boolean;
    totalActividades?: number;
    /** Baja a la sección de actividades. */
    onIrAActividades?: () => void;
    /** Abre el panel lateral «Rendimiento» (T30). */
    onAbrirRendimiento?: () => void;
  }

  let {
    curso = null,
    tienePrograma = false,
    totalActividades = 0,
    onIrAActividades = () => {},
    onAbrirRendimiento = () => {},
  }: Props = $props();

  const codigo = $derived(curso?.cod_asignatura || curso?.cod_curso || '');
  const titulo = $derived(curso?.asignatura_nombre || curso?.nombre || 'Curso');

  // El nombre del curso sólo aporta cuando difiere de la asignatura (secciones
  // con nombre propio); si son iguales, repetirlo es ruido.
  const subtitulo = $derived(curso?.nombre && curso.nombre !== titulo ? curso.nombre : '');

  // agno_real/semestre_real son el año y semestre de la malla (1..n), no el año
  // calendario: se rotulan igual que en el resto de la app.
  const periodo = $derived(
    curso?.agno_real && curso?.semestre_real
      ? `Año ${curso.agno_real} · Semestre ${curso.semestre_real}`
      : '',
  );

  interface FichaItem {
    label: string;
    valor: string;
  }

  const ficha = $derived.by((): FichaItem[] => {
    const asignatura = curso?.asignatura;
    const items: FichaItem[] = [];

    if (codigo) items.push({ label: 'Código', valor: codigo });
    if (curso?.letra_grupo) items.push({ label: 'Grupo', valor: curso.letra_grupo });
    if (asignatura?.creditos_sct) {
      items.push({ label: 'Créditos SCT', valor: String(asignatura.creditos_sct) });
    }
    if (asignatura?.horas_catedra) {
      items.push({ label: 'Horas cátedra', valor: `${asignatura.horas_catedra} h` });
    }
    if (asignatura?.horas_taller) {
      items.push({ label: 'Horas taller', valor: `${asignatura.horas_taller} h` });
    }
    if (asignatura?.horas_laboratorio) {
      items.push({ label: 'Horas laboratorio', valor: `${asignatura.horas_laboratorio} h` });
    }

    return items;
  });


  // Las tres acciones del curso (programa, actividades, mensajería) pesan lo
  // mismo, así que comparten un único estilo (T23).
  const BOTON_ACCION =
    'inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold no-underline border border-gray-300 bg-white text-gray-700 hover:border-uta-blue/30 hover:bg-uta-blue-light hover:text-uta-blue transition-colors';
</script>

<header class="mb-8">
  <div class="flex flex-col gap-1 mb-6">
    {#if periodo}
      <span
        class="inline-flex items-center gap-1.5 text-xs font-bold text-uta-blue bg-uta-blue-light border border-uta-blue/20 rounded-full px-3 py-0.5 w-fit"
      >
        {periodo}
      </span>
    {/if}
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
      {titulo}
    </h1>
    <p class="text-sm text-slate-500">
      {#if curso?.carrera_nombre}{curso.carrera_nombre}{/if}
      {#if subtitulo}{curso?.carrera_nombre ? ' · ' : ''}{subtitulo}{/if}
    </p>
  </div>

  <div class="rounded-3xl border border-gray-200 bg-white p-6">
    {#if ficha.length > 0}
      <dl class="flex flex-wrap gap-x-10 gap-y-4">
        {#each ficha as item (item.label)}
          <div>
            <dt class="text-xs text-gray-600 font-medium mb-1">{item.label}</dt>
            <dd class="font-semibold text-gray-900">{item.valor}</dd>
          </div>
        {/each}
      </dl>
    {/if}

    <div
      class="grid grid-cols-1 gap-3 sm:flex sm:flex-wrap sm:items-center {ficha.length > 0
        ? 'mt-6 pt-5 border-t border-gray-100'
        : ''}"
    >
      {#if tienePrograma}
        <Link
          href={`/estudiante/cursos/${curso?.id_curso}/programa`}
          class={BOTON_ACCION}
        >
          <FileText class="w-4 h-4" />
          Ver programa
        </Link>
      {/if}
      <button
        onclick={onIrAActividades}
        class={BOTON_ACCION}
      >
        <ClipboardList class="w-4 h-4" />
        Ver actividades
        {#if totalActividades > 0}
          <span class="text-gray-500 font-medium">({totalActividades})</span>
        {/if}
      </button>
      <Link
        href={`/estudiante/cursos/${curso?.id_curso}/mensajeria`}
        class={BOTON_ACCION}
      >
        <MessagesSquare class="w-4 h-4" />
        Mensajería
      </Link>
      <button type="button" onclick={onAbrirRendimiento} class={BOTON_ACCION}>
        <BarChart3 class="w-4 h-4" />
        Rendimiento
      </button>
      <!-- Bitácora del curso (T41): todas las agendas del alumno juntas. -->
      <Link href={`/estudiante/cursos/${curso?.id_curso}/bitacora`} class={BOTON_ACCION}>
        <History class="w-4 h-4" />
        Bitácora
      </Link>
    </div>
  </div>
</header>
