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
  import { CalendarClock, ClipboardList, FileText, MessagesSquare } from 'lucide-svelte';
  import type { Curso } from '@/types';
  import { formatFechaCorta, parseFechaSoloDia } from '@/utils/formatters';

  interface Props {
    curso?: Curso | null;
    /** Actividad con la fecha límite más cercana; null si no quedan entregas. */
    proximaEntrega?: { nombre: string; fecha_limite: string } | null;
    /** Sin programa publicado el botón no lleva a ninguna parte, así que no se muestra. */
    tienePrograma?: boolean;
    totalActividades?: number;
    /** Baja a la sección de actividades. */
    onIrAActividades?: () => void;
  }

  let {
    curso = null,
    proximaEntrega = null,
    tienePrograma = false,
    totalActividades = 0,
    onIrAActividades = () => {},
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

  const diasRestantes = $derived.by((): number | null => {
    if (!proximaEntrega) return null;
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const limite = parseFechaSoloDia(proximaEntrega.fecha_limite);
    return Math.round((limite.getTime() - hoy.getTime()) / 86_400_000);
  });

  const plazoLabel = $derived.by(() => {
    if (diasRestantes === null) return '';
    if (diasRestantes === 0) return 'Vence hoy';
    if (diasRestantes === 1) return 'Vence mañana';
    return `Vence en ${diasRestantes} días`;
  });

  const plazoUrgente = $derived(diasRestantes !== null && diasRestantes <= 3);
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
      class="flex flex-col sm:flex-row sm:items-center gap-3 {ficha.length > 0
        ? 'mt-6 pt-5 border-t border-gray-100'
        : ''}"
    >
      {#if tienePrograma}
        <Link
          href={`/estudiante/cursos/${curso?.id_curso}/programa`}
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold no-underline bg-uta-blue text-white hover:bg-uta-blue-hover transition-colors"
        >
          <FileText class="w-4 h-4" />
          Ver programa
        </Link>
      {/if}
      <button
        onclick={onIrAActividades}
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
      >
        <ClipboardList class="w-4 h-4" />
        Ver actividades
        {#if totalActividades > 0}
          <span class="text-gray-500 font-medium">({totalActividades})</span>
        {/if}
      </button>
      <Link
        href="/estudiante/mensajeria"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold no-underline border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
      >
        <MessagesSquare class="w-4 h-4" />
        Mensajería
      </Link>
    </div>
  </div>

  {#if proximaEntrega}
    <div
      class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-2xl border p-4 {plazoUrgente
        ? 'border-uta-red/20 bg-uta-red-light'
        : 'border-gray-200 bg-gray-50'}"
    >
      <CalendarClock
        class="w-4 h-4 shrink-0 {plazoUrgente ? 'text-uta-red' : 'text-gray-500'}"
      />
      <span class="text-sm text-gray-600">Próxima entrega:</span>
      <span class="text-sm font-semibold text-gray-900">{proximaEntrega.nombre}</span>
      <span class="text-sm text-gray-500">
        {formatFechaCorta(proximaEntrega.fecha_limite)}
      </span>
      <span class="text-sm font-semibold {plazoUrgente ? 'text-uta-red' : 'text-gray-700'}">
        {plazoLabel}
      </span>
    </div>
  {/if}
</header>
