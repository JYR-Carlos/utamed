<script lang="ts">
  /**
   * Mensajes de actividades del docente — bandeja transversal a todos sus cursos.
   *
   * Es la mensajería de NIVEL ACTIVIDAD (agenda.agenda). La de nivel curso vive
   * dentro de cada curso, en /docente/cursos/{id}/mensajeria (curso.mensaje).
   *
   * La bandeja no muestra las conversaciones: es un índice de actividades
   * repartido en dos cajones, «Nuevos» (con mensajes sin ver) y «Vistos». Cada
   * fila lleva a la página de la actividad y, si hay algo sin ver, abre la
   * agenda del grupo con el mensaje más reciente (?grupo=…). Así la agenda se
   * ve de una sola forma, la del modal de la actividad.
   *
   * Las actividades que ya no están activas (cerradas, no visibles o
   * planificadas) no tienen conversación que atender: se esconden salvo que se
   * active «Ver todas las actividades», y entonces aparecen en gris. Una con
   * mensajes sin ver se muestra igual, para que nada quede sin leer.
   *
   * Visibilidad: el backend sólo entrega cursos donde el docente es titular.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { Link } from '@inertiajs/svelte';
  import type { BreadcrumbItem } from '@/types';
  import { MessageSquare, Search, EyeOff, ChevronRight, Inbox, CheckCheck } from 'lucide-svelte';

  interface ActividadItem {
    id_actividad: number;
    nombre: string;
    estado: 'ACTIVA' | 'CERRADA' | 'PLANIFICADA' | 'NO VISIBLE' | string;
    fecha_limite: string | null;
    no_leidos: number;
    grupos_con_no_leidos: number;
    grupo_a_abrir: number | null;
    ultima_fecha: string | null;
    pendientes: number;
    curso: {
      id_curso: number;
      nombre: string;
      cod_curso: string;
      agno_real: number;
      semestre_real: number;
    };
  }

  interface Props {
    actividades: ActividadItem[];
  }

  let { actividades = [] }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Mensajes de actividades', href: '/docente/mensajes' },
  ];

  const CLAVE_VER_TODAS = 'docente.mensajes.verTodas';

  let query = $state('');
  let verTodas = $state(leerVerTodas());

  function leerVerTodas(): boolean {
    try {
      return localStorage.getItem(CLAVE_VER_TODAS) === '1';
    } catch {
      return false;
    }
  }

  $effect(() => {
    try {
      localStorage.setItem(CLAVE_VER_TODAS, verTodas ? '1' : '0');
    } catch {
      /* sin almacenamiento: el interruptor sólo dura la visita */
    }
  });

  const esActiva = (a: ActividadItem) => a.estado === 'ACTIVA';

  const filtradas = $derived.by(() => {
    const q = query.trim().toLowerCase();
    if (!q) return actividades;
    return actividades.filter(
      (a) =>
        a.nombre.toLowerCase().includes(q) ||
        a.curso.nombre.toLowerCase().includes(q) ||
        a.curso.cod_curso.toLowerCase().includes(q),
    );
  });

  // Nuevos: lo más reciente arriba.
  const nuevas = $derived(
    filtradas
      .filter((a) => a.no_leidos > 0)
      .sort((a, b) => (b.ultima_fecha ?? '').localeCompare(a.ultima_fecha ?? '')),
  );

  // Vistos: primero las activas por fecha límite más próxima; las demás al
  // final (y sólo con el interruptor), de la más reciente a la más antigua.
  const vistas = $derived.by(() => {
    const sinNuevos = filtradas.filter((a) => a.no_leidos === 0);
    const porFecha = (x: ActividadItem, y: ActividadItem) =>
      (x.fecha_limite ?? '9999').localeCompare(y.fecha_limite ?? '9999') ||
      x.nombre.localeCompare(y.nombre);
    const activas = sinNuevos.filter(esActiva).sort(porFecha);
    const otras = verTodas
      ? sinNuevos.filter((a) => !esActiva(a)).sort((x, y) => porFecha(y, x))
      : [];
    return [...activas, ...otras];
  });

  const ocultas = $derived(filtradas.filter((a) => a.no_leidos === 0 && !esActiva(a)).length);
  const totalNoLeidos = $derived(actividades.reduce((acc, a) => acc + a.no_leidos, 0));

  function href(a: ActividadItem) {
    const base = `/docente/cursos/${a.curso.id_curso}/actividades/${a.id_actividad}/evaluacion`;
    return a.grupo_a_abrir ? `${base}?grupo=${a.grupo_a_abrir}` : base;
  }

  function etiquetaEstado(estado: string) {
    return (
      { CERRADA: 'Cerrada', 'NO VISIBLE': 'No visible', PLANIFICADA: 'Planificada' }[estado] ?? estado
    );
  }

  function fmtFecha(iso: string) {
    return new Date(iso).toLocaleString('es-CL', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
</script>

{#snippet fila(a: ActividadItem)}
  {@const activa = esActiva(a)}
  <Link
    href={href(a)}
    class="group flex items-center gap-3 px-4 py-3 no-underline transition-colors hover:bg-slate-50"
  >
    {#if !activa}
      <EyeOff size={16} class="shrink-0 text-slate-300" aria-label="Actividad no activa" />
    {:else}
      <MessageSquare
        size={16}
        class="shrink-0 {a.no_leidos > 0 ? 'text-indigo-500' : 'text-slate-300'}"
      />
    {/if}
    <span class="min-w-0 flex-1">
      <span
        class="block truncate text-sm {activa
          ? a.no_leidos > 0
            ? 'font-bold text-slate-900'
            : 'font-medium text-slate-700'
          : 'font-medium text-slate-400'}"
      >
        {a.nombre}
      </span>
      <span class="block truncate text-xs {activa ? 'text-slate-500' : 'text-slate-400'}">
        {a.curso.cod_curso} · {a.curso.nombre} · {a.curso.agno_real}-{a.curso.semestre_real}
        {#if !activa}· {etiquetaEstado(a.estado)}{/if}
      </span>
    </span>
    {#if a.no_leidos > 0}
      <span class="flex shrink-0 flex-col items-end gap-0.5">
        <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-[11px] font-bold text-white">
          {a.no_leidos} {a.no_leidos === 1 ? 'msg nuevo' : 'msgs nuevos'}
        </span>
        {#if a.ultima_fecha}
          <span class="text-[10px] text-slate-400">{fmtFecha(a.ultima_fecha)}</span>
        {/if}
      </span>
    {/if}
    <ChevronRight size={16} class="shrink-0 text-slate-300 group-hover:text-slate-500" />
  </Link>
{/snippet}

<DocenteLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-6 sm:px-6">
    <!-- ── Encabezado ── -->
    <div class="flex items-center gap-3">
      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white"
      >
        <MessageSquare size={20} />
      </div>
      <div class="min-w-0">
        <h1 class="text-lg font-extrabold leading-tight text-slate-900">Mensajes de actividades</h1>
        <p class="text-xs text-slate-500">
          Al abrir una actividad se muestra la agenda del grupo con mensajes nuevos.
        </p>
      </div>
      {#if totalNoLeidos > 0}
        <span
          class="ml-auto shrink-0 rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-bold text-indigo-700"
        >
          {totalNoLeidos} sin ver
        </span>
      {/if}
    </div>

    <!-- ── Búsqueda + interruptor ── -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <div class="relative flex-1">
        <Search size={14} class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          bind:value={query}
          type="search"
          placeholder="Buscar curso o actividad…"
          class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-8 pr-3 text-sm focus:border-indigo-400 focus:outline-none"
        />
      </div>
      <label class="flex shrink-0 cursor-pointer items-center gap-2 text-sm text-slate-600">
        <button
          type="button"
          role="switch"
          aria-checked={verTodas}
          aria-label="Ver todas las actividades"
          onclick={() => (verTodas = !verTodas)}
          class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors {verTodas
            ? 'bg-indigo-600'
            : 'bg-slate-300'}"
        >
          <span
            class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform {verTodas
              ? 'translate-x-4'
              : 'translate-x-0.5'}"
          ></span>
        </button>
        Ver todas las actividades
      </label>
    </div>

    <!-- ── Cajón: nuevos ── -->
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <h2
        class="flex items-center gap-2 border-b border-slate-100 bg-indigo-50/60 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-indigo-700"
      >
        <Inbox size={14} /> Nuevos (sin ver)
        <span class="ml-auto font-semibold normal-case tracking-normal text-indigo-500">
          {nuevas.length}
        </span>
      </h2>
      {#if nuevas.length === 0}
        <p class="flex items-center gap-2 px-4 py-6 text-sm text-slate-400">
          <CheckCheck size={16} /> Estás al día: no hay mensajes sin ver.
        </p>
      {:else}
        <div class="divide-y divide-slate-100">
          {#each nuevas as a (a.id_actividad)}
            {@render fila(a)}
          {/each}
        </div>
      {/if}
    </section>

    <!-- ── Cajón: vistos ── -->
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <h2
        class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-slate-500"
      >
        <CheckCheck size={14} /> Vistos
        <span class="ml-auto font-semibold normal-case tracking-normal text-slate-400">
          {vistas.length}
        </span>
      </h2>
      {#if vistas.length === 0}
        <p class="px-4 py-6 text-sm text-slate-400">
          {query ? 'Sin resultados.' : 'No hay actividades activas sin mensajes nuevos.'}
        </p>
      {:else}
        <div class="divide-y divide-slate-100">
          {#each vistas as a (a.id_actividad)}
            {@render fila(a)}
          {/each}
        </div>
      {/if}
      {#if !verTodas && ocultas > 0}
        <button
          type="button"
          onclick={() => (verTodas = true)}
          class="flex w-full items-center gap-2 border-t border-slate-100 px-4 py-2.5 text-left text-xs text-slate-400 hover:bg-slate-50 hover:text-slate-600"
        >
          <EyeOff size={13} />
          {ocultas}
          {ocultas === 1 ? 'actividad no activa oculta' : 'actividades no activas ocultas'} · Mostrar
        </button>
      {/if}
    </section>
  </div>
</DocenteLayout>
