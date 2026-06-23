<script lang="ts">
  /**
   * Métricas de Rendimiento — Jefe de Carrera
   *
   * Vista de observabilidad agregada de la carrera:
   *   - Asistencia promedio por curso
   *   - Avance de evaluación (actividades calificadas) por curso
   *   - Alumnos en riesgo (promedio parcial < 4.0)
   *   - Carga docente (cursos / estudiantes por docente titular)
   *
   * Todos los datos llegan ya filtrados por el contexto de la carrera del jefe.
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import {
    ArrowLeft,
    BarChart2,
    CalendarCheck,
    ClipboardCheck,
    AlertTriangle,
    Users,
    GraduationCap,
    Inbox,
  } from 'lucide-svelte';

  // ─── Types ───────────────────────────────────────────────────────────────────

  interface AsistenciaCurso {
    id_curso: number;
    asignatura: string;
    cod: string;
    docente: string;
    porcentaje: number;
    sesiones: number;
  }

  interface AvanceCurso {
    id_curso: number;
    asignatura: string;
    cod: string;
    total_actividades: number;
    calificadas: number;
    porcentaje: number;
  }

  interface RiesgoCurso {
    id_curso: number;
    asignatura: string;
    cod: string;
    en_riesgo: number;
    total: number;
  }

  interface CargaDocente {
    docente: string;
    inicial: string;
    color: string;
    cursos: number;
    estudiantes: number;
  }

  interface Props {
    carrera?: { nombre: string };
    asistencia_por_curso?: AsistenciaCurso[];
    avance_por_curso?: AvanceCurso[];
    alumnos_en_riesgo?: { total: number; por_curso: RiesgoCurso[] };
    carga_docente?: CargaDocente[];
  }

  let {
    carrera = { nombre: 'Mi Carrera' },
    asistencia_por_curso = [],
    avance_por_curso = [],
    alumnos_en_riesgo = { total: 0, por_curso: [] },
    carga_docente = [],
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Jefe de Carrera', href: '/docente/jefe-carrera/dashboard' },
    { title: 'Métricas', href: '/docente/jefe-carrera/metricas' },
  ];

  // ─── Derived summaries ─────────────────────────────────────────────────────

  const asistenciaPromedio = $derived.by(() => {
    if (asistencia_por_curso.length === 0) return null;
    const sum = asistencia_por_curso.reduce((a, c) => a + c.porcentaje, 0);
    return Math.round(sum / asistencia_por_curso.length);
  });

  const avancePromedio = $derived.by(() => {
    if (avance_por_curso.length === 0) return null;
    const sum = avance_por_curso.reduce((a, c) => a + c.porcentaje, 0);
    return Math.round(sum / avance_por_curso.length);
  });

  const totalEstudiantesCarga = $derived(
    carga_docente.reduce((a, d) => a + d.estudiantes, 0),
  );

  function barColor(pct: number): string {
    if (pct >= 80) return 'bg-emerald-500';
    if (pct >= 50) return 'bg-amber-400';
    return 'bg-red-400';
  }

  function riskRatio(c: RiesgoCurso): number {
    return c.total > 0 ? Math.round((c.en_riesgo / c.total) * 100) : 0;
  }
</script>

<AdminLayout {breadcrumbs}>
  <div class="min-h-screen px-6 py-8">
    <!-- ─── Header ──────────────────────────────────────────────────────── -->
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          href="/docente/jefe-carrera/dashboard"
          class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 transition-colors hover:text-gray-700"
        >
          <ArrowLeft size={14} />
          Volver al Dashboard
        </Link>
        <p class="text-[11px] font-semibold uppercase tracking-widest text-indigo-600 mb-1">
          Métricas de Rendimiento
        </p>
        <h1 class="text-2xl font-bold text-gray-900">{carrera.nombre}</h1>
        <p class="mt-0.5 text-sm text-gray-500">
          Indicadores agregados de los cursos de la carrera
        </p>
      </div>
    </div>

    <!-- ─── Summary KPI strip ───────────────────────────────────────────── -->
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <!-- Asistencia promedio -->
      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
          <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
            Asistencia
          </p>
          <span class="rounded-lg bg-blue-50 p-1.5"><CalendarCheck size={16} class="text-blue-600" /></span>
        </div>
        <p class="text-3xl font-extrabold leading-none text-gray-900">
          {asistenciaPromedio === null ? '—' : `${asistenciaPromedio}%`}
        </p>
        <p class="mt-1.5 text-xs text-gray-400">Promedio de la carrera</p>
      </div>

      <!-- Avance evaluación -->
      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
          <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
            Avance Eval.
          </p>
          <span class="rounded-lg bg-emerald-50 p-1.5"><ClipboardCheck size={16} class="text-emerald-600" /></span>
        </div>
        <p class="text-3xl font-extrabold leading-none text-gray-900">
          {avancePromedio === null ? '—' : `${avancePromedio}%`}
        </p>
        <p class="mt-1.5 text-xs text-gray-400">Actividades calificadas</p>
      </div>

      <!-- Alumnos en riesgo -->
      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
          <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
            En Riesgo
          </p>
          <span class="rounded-lg bg-red-50 p-1.5"><AlertTriangle size={16} class="text-red-600" /></span>
        </div>
        <p class="text-3xl font-extrabold leading-none text-gray-900">
          {alumnos_en_riesgo.total}
        </p>
        <p class="mt-1.5 text-xs text-gray-400">Promedio parcial &lt; 4.0</p>
      </div>

      <!-- Carga docente -->
      <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
          <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
            Docentes
          </p>
          <span class="rounded-lg bg-violet-50 p-1.5"><Users size={16} class="text-violet-600" /></span>
        </div>
        <p class="text-3xl font-extrabold leading-none text-gray-900">{carga_docente.length}</p>
        <p class="mt-1.5 text-xs text-gray-400">{totalEstudiantesCarga} estudiantes</p>
      </div>
    </div>

    <!-- ─── Row: Asistencia + Avance ────────────────────────────────────── -->
    <div class="mb-4 grid grid-cols-12 gap-4">
      <!-- Asistencia por curso -->
      <div class="col-span-12 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:col-span-6">
        <div class="flex items-center gap-2.5 border-b border-gray-100 px-6 py-4">
          <div class="rounded-lg bg-blue-50 p-1.5"><CalendarCheck size={15} class="text-blue-500" /></div>
          <h3 class="text-sm font-bold text-gray-900">Asistencia por Curso</h3>
        </div>
        {#if asistencia_por_curso.length === 0}
          <div class="px-6 py-12 text-center">
            <Inbox size={32} class="mx-auto mb-2 text-gray-200" />
            <p class="text-sm font-medium text-gray-400">Sin registros de asistencia</p>
          </div>
        {:else}
          <div class="divide-y divide-gray-50">
            {#each asistencia_por_curso as c (c.id_curso)}
              <div class="px-6 py-3.5">
                <div class="mb-1.5 flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-800">{c.asignatura}</p>
                    <p class="font-mono text-[11px] text-gray-400">{c.cod} · {c.docente}</p>
                  </div>
                  <span class="flex-shrink-0 text-sm font-bold text-gray-900">{c.porcentaje}%</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                    <div
                      class="h-full rounded-full transition-all {barColor(c.porcentaje)}"
                      style="width: {c.porcentaje}%"
                    ></div>
                  </div>
                  <span class="flex-shrink-0 text-[10px] text-gray-400">{c.sesiones} ses.</span>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>

      <!-- Avance de evaluación por curso -->
      <div class="col-span-12 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:col-span-6">
        <div class="flex items-center gap-2.5 border-b border-gray-100 px-6 py-4">
          <div class="rounded-lg bg-emerald-50 p-1.5"><ClipboardCheck size={15} class="text-emerald-500" /></div>
          <h3 class="text-sm font-bold text-gray-900">Avance de Evaluación</h3>
        </div>
        {#if avance_por_curso.length === 0}
          <div class="px-6 py-12 text-center">
            <Inbox size={32} class="mx-auto mb-2 text-gray-200" />
            <p class="text-sm font-medium text-gray-400">Sin actividades evaluables</p>
          </div>
        {:else}
          <div class="divide-y divide-gray-50">
            {#each avance_por_curso as c (c.id_curso)}
              <div class="px-6 py-3.5">
                <div class="mb-1.5 flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-800">{c.asignatura}</p>
                    <p class="font-mono text-[11px] text-gray-400">{c.cod}</p>
                  </div>
                  <span class="flex-shrink-0 text-sm font-bold text-gray-900">{c.porcentaje}%</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                    <div
                      class="h-full rounded-full transition-all {barColor(c.porcentaje)}"
                      style="width: {c.porcentaje}%"
                    ></div>
                  </div>
                  <span class="flex-shrink-0 text-[10px] text-gray-400">
                    {c.calificadas}/{c.total_actividades}
                  </span>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>
    </div>

    <!-- ─── Row: Riesgo + Carga docente ─────────────────────────────────── -->
    <div class="grid grid-cols-12 gap-4">
      <!-- Alumnos en riesgo -->
      <div class="col-span-12 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:col-span-6">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div class="flex items-center gap-2.5">
            <div class="rounded-lg bg-red-50 p-1.5"><AlertTriangle size={15} class="text-red-500" /></div>
            <h3 class="text-sm font-bold text-gray-900">Alumnos en Riesgo</h3>
          </div>
          <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-100 px-1.5 text-[11px] font-bold text-red-600">
            {alumnos_en_riesgo.total}
          </span>
        </div>
        {#if alumnos_en_riesgo.por_curso.length === 0}
          <div class="px-6 py-12 text-center">
            <GraduationCap size={32} class="mx-auto mb-2 text-emerald-300" />
            <p class="text-sm font-medium text-gray-400">Sin alumnos en riesgo</p>
            <p class="mt-0.5 text-xs text-gray-300">No hay promedios parciales bajo 4.0</p>
          </div>
        {:else}
          <div class="divide-y divide-gray-50">
            {#each alumnos_en_riesgo.por_curso as c (c.id_curso)}
              {@const ratio = riskRatio(c)}
              <div class="flex items-center justify-between gap-3 px-6 py-3.5">
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-gray-800">{c.asignatura}</p>
                  <p class="font-mono text-[11px] text-gray-400">{c.cod}</p>
                </div>
                <div class="flex flex-shrink-0 items-center gap-2.5">
                  <span class="text-xs text-gray-400">{c.en_riesgo}/{c.total}</span>
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {ratio >= 30
                      ? 'bg-red-100 text-red-700'
                      : ratio > 0
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-gray-100 text-gray-500'}"
                  >
                    {ratio}%
                  </span>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>

      <!-- Carga docente -->
      <div class="col-span-12 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:col-span-6">
        <div class="flex items-center gap-2.5 border-b border-gray-100 px-6 py-4">
          <div class="rounded-lg bg-violet-50 p-1.5"><BarChart2 size={15} class="text-violet-500" /></div>
          <h3 class="text-sm font-bold text-gray-900">Carga Docente</h3>
        </div>
        {#if carga_docente.length === 0}
          <div class="px-6 py-12 text-center">
            <Users size={32} class="mx-auto mb-2 text-gray-200" />
            <p class="text-sm font-medium text-gray-400">Sin docentes asignados</p>
          </div>
        {:else}
          <div class="divide-y divide-gray-50">
            {#each carga_docente as d (d.docente)}
              <div class="flex items-center gap-3 px-6 py-3.5">
                <div
                  class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                  style="background-color: {d.color}"
                  aria-hidden="true"
                >
                  {d.inicial}
                </div>
                <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-700">{d.docente}</p>
                <div class="flex flex-shrink-0 items-center gap-4">
                  <div class="text-right">
                    <p class="text-sm font-bold text-gray-900">{d.cursos}</p>
                    <p class="text-[10px] text-gray-400">cursos</p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-bold text-gray-900">{d.estudiantes}</p>
                    <p class="text-[10px] text-gray-400">alumnos</p>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>
