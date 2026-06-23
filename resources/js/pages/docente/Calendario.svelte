<script lang="ts">
  /**
   * Calendario del docente.
   *
   * Vista amplia, de solo lectura, tipo "Google Calendar" ambientado a UTAMED.
   * Muestra todas las actividades a vencer de los cursos del docente sobre una
   * grilla mensual, coloreadas e identificadas por curso
   * (nombre curso = nombre asignatura + año + semestre). Permite filtrar por
   * curso, navegar entre meses y revisar las próximas actividades pendientes.
   *
   * No crea, edita ni elimina actividades: para gestionarlas, el docente entra
   * al curso correspondiente desde el detalle del día.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import {
    CalendarMonth,
    CourseFilter,
    UpcomingList,
    DayEventsDialog,
    accentFor,
    MESES,
    todayKey,
    diasRestantes,
    formatLargo,
    etiquetaRelativa,
    type CalendarCurso,
    type CalendarEvento,
    type CursoAccent,
  } from '@/modules/resources/calendario';
  import {
    ChevronLeft,
    ChevronRight,
    CalendarDays,
    CalendarRange,
    List as ListIcon,
    Users,
  } from 'lucide-svelte';

  interface Props {
    cursos: CalendarCurso[];
    eventos: CalendarEvento[];
  }

  let { cursos = [], eventos = [] }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Calendario', href: '/docente/calendario' },
  ];

  // ── Paleta y mapas estables por curso ──────────────────────────────────────
  const accentPorCurso = $derived.by(() => {
    const map: Record<number, CursoAccent> = {};
    cursos.forEach((c, i) => (map[c.id_curso] = accentFor(i)));
    return map;
  });

  const cursoPorId = $derived.by(() => {
    const map: Record<number, CalendarCurso> = {};
    cursos.forEach((c) => (map[c.id_curso] = c));
    return map;
  });

  // ── Estado de navegación ────────────────────────────────────────────────────
  const hoy = new Date();
  let year = $state(hoy.getFullYear());
  let month = $state(hoy.getMonth());
  let vista = $state<'mes' | 'agenda'>('mes');
  let selectedDay = $state<string | null>(null);

  // ── Filtro por curso ────────────────────────────────────────────────────────
  // Se modela con el conjunto de cursos *ocultos* (vacío = todos visibles), lo
  // que evita capturar el valor inicial de los props en el estado.
  let ocultos = $state<Set<number>>(new Set());

  const activos = $derived.by(() => {
    const s = new Set<number>();
    for (const c of cursos) if (!ocultos.has(c.id_curso)) s.add(c.id_curso);
    return s;
  });

  function toggleCurso(id: number) {
    const next = new Set(ocultos);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    ocultos = next;
  }
  function soloEste(id: number) {
    ocultos = new Set(cursos.filter((c) => c.id_curso !== id).map((c) => c.id_curso));
  }
  function todosCursos() {
    ocultos = new Set();
  }

  // ── Eventos filtrados y agrupados ───────────────────────────────────────────
  const eventosVisibles = $derived(eventos.filter((e) => activos.has(e.id_curso)));

  const eventosPorDia = $derived.by(() => {
    const map: Record<string, CalendarEvento[]> = {};
    for (const e of eventosVisibles) {
      (map[e.fecha] ??= []).push(e);
    }
    // Orden estable dentro del día: sumativas primero, luego por título
    for (const k in map) {
      map[k].sort(
        (a, b) =>
          Number(b.tipo_actividad === 'SUMATIVA') - Number(a.tipo_actividad === 'SUMATIVA') ||
          a.titulo.localeCompare(b.titulo),
      );
    }
    return map;
  });

  const eventosDiaSeleccionado = $derived(selectedDay ? (eventosPorDia[selectedDay] ?? []) : []);

  // ── Vista agenda: próximos días con eventos (desde hoy) ─────────────────────
  const agendaDias = $derived.by(() => {
    const hoyKey = todayKey();
    return Object.keys(eventosPorDia)
      .filter((iso) => iso >= hoyKey)
      .sort((a, b) => a.localeCompare(b))
      .map((iso) => ({ iso, eventos: eventosPorDia[iso] }));
  });

  // ── KPIs ────────────────────────────────────────────────────────────────────
  const totalPendientes = $derived(eventosVisibles.filter((e) => diasRestantes(e.fecha) >= 0).length);
  const venceSemana = $derived(
    eventosVisibles.filter((e) => {
      const d = diasRestantes(e.fecha);
      return d >= 0 && d <= 7;
    }).length,
  );

  // ── Navegación de meses ─────────────────────────────────────────────────────
  function prevMonth() {
    if (month === 0) {
      month = 11;
      year -= 1;
    } else month -= 1;
  }
  function nextMonth() {
    if (month === 11) {
      month = 0;
      year += 1;
    } else month += 1;
  }
  function irHoy() {
    year = hoy.getFullYear();
    month = hoy.getMonth();
  }

  function onSelectEvento(evento: CalendarEvento) {
    selectedDay = evento.fecha;
  }
</script>

<DocenteLayout {breadcrumbs}>
  <div class="mx-auto max-w-[1320px] px-6 py-7 max-md:px-4">
    <!-- ── Encabezado ──────────────────────────────────────────────────────── -->
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-indigo-600">
          <CalendarDays size={20} />
          <span class="text-xs font-bold uppercase tracking-widest">Calendario académico</span>
        </div>
        <h1 class="mt-1 text-[2rem] font-bold leading-tight tracking-tight text-slate-900">
          Actividades a vencer
        </h1>
        <p class="mt-1 text-sm text-slate-500">
          {totalPendientes}
          {totalPendientes === 1 ? 'pendiente' : 'pendientes'} en total · {venceSemana} esta semana
        </p>
      </div>

      <!-- Toggle de vista -->
      <div class="inline-flex gap-0.5 rounded-xl border border-slate-200 bg-white p-1">
        <button
          type="button"
          onclick={() => (vista = 'mes')}
          class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-semibold transition-colors {vista ===
          'mes'
            ? 'bg-indigo-50 text-indigo-600'
            : 'text-slate-500 hover:text-slate-800'}"
        >
          <CalendarRange size={15} /> Mes
        </button>
        <button
          type="button"
          onclick={() => (vista = 'agenda')}
          class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-semibold transition-colors {vista ===
          'agenda'
            ? 'bg-indigo-50 text-indigo-600'
            : 'text-slate-500 hover:text-slate-800'}"
        >
          <ListIcon size={15} /> Agenda
        </button>
      </div>
    </header>

    <div class="grid grid-cols-[1fr_300px] gap-6 max-lg:grid-cols-1">
      <!-- ── Columna principal ────────────────────────────────────────────── -->
      <div>
        <!-- Barra de navegación de mes -->
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="text-xl font-bold capitalize text-slate-900">
            {MESES[month]}
            <span class="font-medium text-slate-400">{year}</span>
          </h2>
          <div class="flex items-center gap-2">
            <button
              type="button"
              onclick={irHoy}
              class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[13px] font-semibold text-slate-600 transition-colors hover:border-indigo-300 hover:text-indigo-600"
            >
              Hoy
            </button>
            <div class="inline-flex rounded-lg border border-slate-200 bg-white">
              <button
                type="button"
                onclick={prevMonth}
                class="rounded-l-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-50 hover:text-indigo-600"
                aria-label="Mes anterior"
              >
                <ChevronLeft size={18} />
              </button>
              <span class="w-px bg-slate-200"></span>
              <button
                type="button"
                onclick={nextMonth}
                class="rounded-r-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-50 hover:text-indigo-600"
                aria-label="Mes siguiente"
              >
                <ChevronRight size={18} />
              </button>
            </div>
          </div>
        </div>

        {#if vista === 'mes'}
          <CalendarMonth
            {year}
            {month}
            {eventosPorDia}
            {accentPorCurso}
            onSelectDay={(iso) => (selectedDay = iso)}
            {onSelectEvento}
          />
        {:else}
          <!-- ── Vista agenda ──────────────────────────────────────────── -->
          <div class="rounded-2xl border border-slate-200 bg-white">
            {#if agendaDias.length === 0}
              <div class="flex flex-col items-center justify-center py-20 text-center">
                <CalendarDays size={40} class="text-slate-300" />
                <p class="mt-3 font-semibold text-slate-600">No hay actividades pendientes</p>
                <p class="text-sm text-slate-400">Estás al día con tus cursos 🎉</p>
              </div>
            {:else}
              <ul class="divide-y divide-slate-100">
                {#each agendaDias as dia (dia.iso)}
                  <li class="flex gap-4 px-5 py-4 max-sm:flex-col max-sm:gap-2">
                    <div class="w-44 shrink-0">
                      <p class="text-sm font-bold capitalize text-slate-800">{formatLargo(dia.iso)}</p>
                      <p class="text-xs font-semibold text-indigo-500">{etiquetaRelativa(dia.iso)}</p>
                    </div>
                    <ul class="flex flex-1 flex-col gap-2">
                      {#each dia.eventos as evento (evento.id_actividad + '-' + evento.id_componente)}
                        {@const curso = cursoPorId[evento.id_curso]}
                        {@const accent = accentPorCurso[evento.id_curso]}
                        <li>
                          <button
                            type="button"
                            onclick={() => (selectedDay = dia.iso)}
                            class="flex w-full items-center gap-3 rounded-xl border border-slate-100 p-3 text-left transition-shadow hover:shadow-sm"
                            style="border-left:4px solid {accent.base}"
                          >
                            <div class="min-w-0 flex-1">
                              <p class="truncate font-semibold text-slate-900">{evento.titulo}</p>
                              <p class="truncate text-sm" style="color:{accent.text}">
                                {curso?.nombre ?? 'Curso'}
                              </p>
                            </div>
                            <span
                              class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold {evento.tipo_actividad ===
                              'SUMATIVA'
                                ? 'bg-amber-100 text-amber-800'
                                : 'bg-sky-100 text-sky-800'}"
                            >
                              {evento.tipo_actividad === 'SUMATIVA' ? 'Sumativa' : 'Formativa'}
                            </span>
                            {#if evento.es_grupal}
                              <Users size={15} class="shrink-0 text-slate-400" />
                            {/if}
                          </button>
                        </li>
                      {/each}
                    </ul>
                  </li>
                {/each}
              </ul>
            {/if}
          </div>
        {/if}
      </div>

      <!-- ── Barra lateral ────────────────────────────────────────────────── -->
      <aside class="flex flex-col gap-4 max-lg:flex-row max-lg:flex-wrap max-md:flex-col">
        <div class="flex-1 max-lg:min-w-[280px]">
          <CourseFilter
            {cursos}
            {accentPorCurso}
            {activos}
            onToggle={toggleCurso}
            onSoloEste={soloEste}
            onTodos={todosCursos}
          />
        </div>
        <div class="flex-1 max-lg:min-w-[280px]">
          <UpcomingList
            eventos={eventosVisibles}
            {cursoPorId}
            {accentPorCurso}
            {onSelectEvento}
          />
        </div>
      </aside>
    </div>
  </div>

  <!-- Detalle del día -->
  <DayEventsDialog
    iso={selectedDay}
    eventos={eventosDiaSeleccionado}
    {cursoPorId}
    {accentPorCurso}
    onClose={() => (selectedDay = null)}
  />
</DocenteLayout>
