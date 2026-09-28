<script lang="ts">
  /**
   * Bitácora del curso (T41): todas las agendas de las actividades del alumno
   * —entregas, mensajes, retroalimentaciones y evaluaciones— en un solo flujo,
   * de la más reciente a la más antigua. Se filtra por actividad y por
   * componente; cada entrada lleva a la agenda de su actividad.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import {
    ArrowLeft,
    ChevronRight,
    FileUp,
    FileX,
    MessageSquare,
    MessageSquareReply,
    Award,
    Lock,
    History,
  } from 'lucide-svelte';
  import { formatFechaHora } from '@/utils/formatters';

  interface Entrada {
    id_agenda: number;
    fecha: string;
    tipo: string;
    mensaje: string;
    emisor: string;
    es_propio: boolean;
    es_de_docente: boolean;
    archivo: string | null;
    resultado: string | null;
    actividad: { id: number; nombre: string };
    componente: { id: number | null; tipo: string | null };
  }

  interface Props {
    curso: { id_curso: number; nombre: string; cod_asignatura?: string | null };
    entradas?: Entrada[];
    actividades?: Array<{ id: number; nombre: string }>;
    componentes?: Array<{ id: number; tipo: string }>;
  }

  let { curso, entradas = [], actividades = [], componentes = [] }: Props = $props();

  const breadcrumbs = $derived<BreadcrumbItem[]>([
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso.nombre, href: `/estudiante/cursos/${curso.id_curso}` },
    { title: 'Bitácora', href: '' },
  ]);

  let filtroActividad = $state<number | ''>('');
  let filtroComponente = $state<number | ''>('');

  const filtradas = $derived(
    entradas.filter(
      (e) =>
        (filtroActividad === '' || e.actividad.id === filtroActividad) &&
        (filtroComponente === '' || e.componente.id === filtroComponente),
    ),
  );

  const TIPOS: Record<string, { icono: any; etiqueta: string; clase: string }> = {
    'Entrega de archivo': { icono: FileUp, etiqueta: 'Entrega', clase: 'bg-uta-blue-light text-uta-blue' },
    'Cancelación de entrega': { icono: FileX, etiqueta: 'Entrega anulada', clase: 'bg-gray-100 text-gray-600' },
    'Mensaje al profesor': { icono: MessageSquare, etiqueta: 'Mensaje', clase: 'bg-slate-100 text-slate-700' },
    Feedback: { icono: MessageSquareReply, etiqueta: 'Retroalimentación', clase: 'bg-amber-50 text-amber-700' },
    Evaluación: { icono: Award, etiqueta: 'Evaluación', clase: 'bg-emerald-50 text-emerald-700' },
    'Cierre de actividad': { icono: Lock, etiqueta: 'Cierre', clase: 'bg-gray-100 text-gray-600' },
  };

  const estilo = (tipo: string) =>
    TIPOS[tipo] ?? { icono: MessageSquare, etiqueta: tipo, clase: 'bg-slate-100 text-slate-700' };

  const SELECT =
    'rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-uta-blue focus:outline-none focus:ring-2 focus:ring-uta-blue/20';
</script>

<svelte:head>
  <title>Bitácora · {curso.nombre} | UTAMED</title>
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div class="mx-auto max-w-4xl px-4 py-8 sm:px-8">
    <Link
      href={`/estudiante/cursos/${curso.id_curso}`}
      class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-uta-blue"
    >
      <ArrowLeft class="h-4 w-4" />
      Volver al curso
    </Link>

    <header class="mb-6 flex flex-col gap-1">
      {#if curso.cod_asignatura}
        <span class="font-mono text-xs text-gray-500">{curso.cod_asignatura}</span>
      {/if}
      <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl">Bitácora del curso</h1>
      <p class="text-sm text-gray-600">
        Todo lo que pasó en las actividades de {curso.nombre}: entregas, mensajes, retroalimentaciones y
        evaluaciones.
      </p>
    </header>

    {#if entradas.length > 0}
      <div class="mb-5 flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">
          Actividad
          <select class={SELECT} bind:value={filtroActividad}>
            <option value="">Todas</option>
            {#each actividades as a (a.id)}
              <option value={a.id}>{a.nombre}</option>
            {/each}
          </select>
        </label>
        {#if componentes.length > 1}
          <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">
            Componente
            <select class={SELECT} bind:value={filtroComponente}>
              <option value="">Todos</option>
              {#each componentes as c (c.id)}
                <option value={c.id}>{c.tipo}</option>
              {/each}
            </select>
          </label>
        {/if}
        <span class="ml-auto text-xs text-gray-500">
          {filtradas.length} de {entradas.length} registros
        </span>
      </div>

      {#if filtradas.length > 0}
        <ol class="relative flex flex-col gap-3 border-l border-gray-200 pl-5">
          {#each filtradas as e (e.id_agenda)}
            {@const st = estilo(e.tipo)}
            <li class="relative">
              <span
                class="absolute -left-[29px] top-3 flex h-4 w-4 items-center justify-center rounded-full border-2 border-white {st.clase}"
                aria-hidden="true"
              ></span>
              <Link
                href={`/estudiante/cursos/${curso.id_curso}/actividad/${e.actividad.id}?abrir=agenda`}
                class="flex flex-col gap-1.5 rounded-2xl border border-gray-200 bg-white p-4 no-underline transition-colors hover:border-uta-blue/30 hover:bg-uta-blue-light/30"
              >
                <div class="flex flex-wrap items-center gap-2">
                  <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {st.clase}">
                    <st.icono class="h-3 w-3" />
                    {st.etiqueta}
                  </span>
                  <span class="text-xs text-gray-500">{formatFechaHora(e.fecha)}</span>
                  <ChevronRight class="ml-auto h-4 w-4 text-gray-400" />
                </div>
                <p class="m-0 text-sm font-semibold text-gray-900">
                  {e.actividad.nombre}
                  {#if e.componente.tipo && componentes.length > 1}
                    <span class="font-normal text-gray-500">· {e.componente.tipo}</span>
                  {/if}
                </p>
                <p class="m-0 text-xs text-gray-500">
                  {e.es_propio ? 'Tú' : e.emisor}{e.es_de_docente ? ' · docente' : ''}
                </p>
                {#if e.archivo}
                  <p class="m-0 text-sm text-gray-700">Archivo: <span class="font-medium">{e.archivo}</span></p>
                {/if}
                {#if e.resultado}
                  <p class="m-0 text-sm text-gray-700">Resultado: <span class="font-semibold">{e.resultado}</span></p>
                {/if}
                {#if e.mensaje}
                  <p class="m-0 line-clamp-3 whitespace-pre-line text-sm text-gray-600">{e.mensaje}</p>
                {/if}
              </Link>
            </li>
          {/each}
        </ol>
      {:else}
        <p class="rounded-2xl border border-dashed border-gray-200 p-6 text-center text-sm text-gray-500">
          No hay registros con esos filtros.
        </p>
      {/if}
    {:else}
      <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-gray-200 p-10 text-center">
        <History class="h-8 w-8 text-gray-300" />
        <p class="text-sm font-semibold text-gray-700">Aún no hay actividad registrada</p>
        <p class="text-sm text-gray-500">
          Aquí se irán juntando tus entregas, los mensajes y las retroalimentaciones de todas las actividades del curso.
        </p>
      </div>
    {/if}
  </div>
</StudentLayout>
