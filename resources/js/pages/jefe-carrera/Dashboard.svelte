<script lang="ts">
  /**
   * Dashboard Ejecutivo — Jefe de Carrera
   * Pantalla 1: Métricas KPI en Bento Box + Panel de Alertas
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link, router } from '@inertiajs/svelte';
  import {
    AlertTriangle,
    BookOpen,
    Bell,
    ChevronRight,
    Send,
    ClipboardList,
    CheckCircle2,
    BarChart2,
    GraduationCap,
    ArrowUpRight,
  } from 'lucide-svelte';

  // ─── Types ───────────────────────────────────────────────────────────────────

  interface Alerta {
    id: number;
    tipo: 'critica' | 'advertencia' | 'info';
    titulo: string;
    count: number;
    accion_label: string;
    accion_url: string;
  }

  interface Props {
    carrera?: { nombre: string; semestre: string; ano: number };
    stats?: {
      syllabus_entregados: number;
      syllabus_total: number;
      cursos_activos: number;
      cursos_tendencia: number[];
      estudiantes_matriculados: number;
    };
    alertas?: Alerta[];
    resumen_estados?: {
      no_iniciado: number;
      en_revision: number;
      aprobado: number;
    };
  }

  let {
    carrera = { nombre: 'Diseño Multimedia', semestre: 'Primero', ano: 2026 },
    stats = {
      syllabus_entregados: 18,
      syllabus_total: 25,
      cursos_activos: 25,
      cursos_tendencia: [22, 20, 23, 25, 24, 25],
      estudiantes_matriculados: 412,
    },
    alertas = [
      {
        id: 1,
        tipo: 'critica' as const,
        titulo: '3 Docentes no han entregado el Syllabus este semestre',
        count: 3,
        accion_label: 'Enviar Recordatorio',
        accion_url: '/docente/jefe-carrera/seguimiento?estado=NO_INICIADO',
      },
      {
        id: 2,
        tipo: 'advertencia' as const,
        titulo: '5 Syllabus llevan más de 7 días en revisión sin respuesta',
        count: 5,
        accion_label: 'Ver detalle',
        accion_url: '/docente/jefe-carrera/seguimiento?estado=EN_REVISION',
      },
      {
        id: 3,
        tipo: 'info' as const,
        titulo: '2 Programas rechazados están pendientes de corrección docente',
        count: 2,
        accion_label: 'Ver lista',
        accion_url: '/docente/jefe-carrera/seguimiento?estado=RECHAZADO',
      },
    ] as Alerta[],
    resumen_estados = { no_iniciado: 7, en_revision: 5, aprobado: 18 },
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Jefe de Carrera', href: '/docente/jefe-carrera/dashboard' },
  ];

  // ─── Donut chart math ────────────────────────────────────────────────────────

  const DONUT_R = 46;
  const DONUT_CX = 60;
  const DONUT_CY = 60;
  const DONUT_SW = 10;
  const circumference = 2 * Math.PI * DONUT_R; // ≈ 289

  const pct = $derived(
    stats.syllabus_total > 0
      ? Math.round((stats.syllabus_entregados / stats.syllabus_total) * 100)
      : 0,
  );
  const arcLength = $derived((pct / 100) * circumference);

  // ─── Sparkline math ──────────────────────────────────────────────────────────

  const SPARK_W = 96;
  const SPARK_H = 32;

  const spark = $derived.by(() => {
    const vals = stats.cursos_tendencia;
    if (vals.length < 2)
      return { polyline: '', dots: [] as { x: number; y: number; isLast: boolean }[] };
    const minV = Math.min(...vals);
    const maxV = Math.max(...vals);
    const range = maxV - minV || 1;
    const dots = vals.map((v, i) => ({
      x: parseFloat(((i / (vals.length - 1)) * SPARK_W).toFixed(2)),
      y: parseFloat((SPARK_H - ((v - minV) / range) * (SPARK_H - 8) - 4).toFixed(2)),
      isLast: i === vals.length - 1,
    }));
    return { polyline: dots.map((d) => `${d.x},${d.y}`).join(' '), dots };
  });

  // ─── Alert visual config ─────────────────────────────────────────────────────

  function getAlertStyle(tipo: string) {
    if (tipo === 'critica')
      return {
        border: 'border-l-red-400',
        bg: 'bg-red-50',
        btnCls: 'bg-red-600 hover:bg-red-700 text-white',
      };
    if (tipo === 'advertencia')
      return {
        border: 'border-l-amber-400',
        bg: 'bg-amber-50',
        btnCls: 'bg-amber-500 hover:bg-amber-600 text-white',
      };
    return {
      border: 'border-l-blue-400',
      bg: 'bg-blue-50',
      btnCls: 'bg-blue-600 hover:bg-blue-700 text-white',
    };
  }
</script>

<AdminLayout {breadcrumbs}>
  <div class="min-h-screen px-6 py-8">
    <!-- ─── Page Header ───────────────────────────────────────────────────── -->
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-widest text-indigo-600 mb-1">
          Jefe de Carrera
        </p>
        <h1 class="text-2xl font-bold text-gray-900">{carrera.nombre}</h1>
        <p class="mt-0.5 text-sm text-gray-500">
          Semestre {carrera.semestre}&nbsp;&middot;&nbsp;{carrera.ano}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Link
          href="/docente/jefe-carrera/seguimiento"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50"
        >
          <ClipboardList size={15} />
          Seguimiento Operativo
        </Link>
        <button
          class="relative rounded-lg border border-gray-200 bg-white p-2 text-gray-500 shadow-sm transition-colors hover:bg-gray-50"
          aria-label="Ver notificaciones"
        >
          <Bell size={18} />
          {#if alertas.some((a) => a.tipo === 'critica')}
            <span
              class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"
            ></span>
          {/if}
        </button>
      </div>
    </div>

    <!-- ─── Bento KPI Grid (Row 1) ────────────────────────────────────────── -->
    <div class="mb-4 grid grid-cols-12 gap-4">
      <!-- Card 1: Cumplimiento de Syllabus (Donut) ── 5 cols -->
      <div
        class="col-span-12 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm md:col-span-5"
      >
        <div class="mb-5 flex items-start justify-between">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
              Cumplimiento
            </p>
            <h3 class="mt-0.5 text-base font-bold text-gray-900">Syllabus Entregados</h3>
          </div>
          <span class="rounded-xl bg-emerald-50 p-2">
            <BookOpen size={18} class="text-emerald-600" />
          </span>
        </div>

        <div class="flex items-center gap-7">
          <!-- Donut SVG -->
          <div class="relative flex-shrink-0">
            <svg
              width="120"
              height="120"
              viewBox="0 0 120 120"
              role="img"
              aria-label="Cumplimiento syllabus: {pct}%"
            >
              <!-- Track -->
              <circle
                cx={DONUT_CX}
                cy={DONUT_CY}
                r={DONUT_R}
                fill="none"
                stroke="#E5E7EB"
                stroke-width={DONUT_SW}
              />
              <!-- Progress arc -->
              <circle
                cx={DONUT_CX}
                cy={DONUT_CY}
                r={DONUT_R}
                fill="none"
                stroke="#10B981"
                stroke-width={DONUT_SW}
                stroke-linecap="round"
                stroke-dasharray="{arcLength} {circumference}"
                transform="rotate(-90 {DONUT_CX} {DONUT_CY})"
              />
              <!-- Center % -->
              <text
                x={DONUT_CX}
                y={DONUT_CY - 4}
                text-anchor="middle"
                font-size="22"
                font-weight="700"
                fill="#111827">{pct}%</text
              >
              <text
                x={DONUT_CX}
                y={DONUT_CY + 14}
                text-anchor="middle"
                font-size="9"
                font-weight="500"
                fill="#9CA3AF">completado</text
              >
            </svg>
          </div>

          <!-- Legend -->
          <div class="flex-1 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 flex-shrink-0 rounded-full bg-emerald-500"></span>
                <span class="text-xs text-gray-600">Entregados</span>
              </div>
              <span class="text-sm font-bold text-gray-900">{stats.syllabus_entregados}</span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 flex-shrink-0 rounded-full bg-gray-200"></span>
                <span class="text-xs text-gray-600">Pendientes</span>
              </div>
              <span class="text-sm font-bold text-gray-400">
                {stats.syllabus_total - stats.syllabus_entregados}
              </span>
            </div>
            <div class="mt-1 border-t border-gray-100 pt-2">
              <div class="flex items-center justify-between">
                <span class="text-xs text-gray-400">Total cursos</span>
                <span class="text-xs font-semibold text-gray-600">{stats.syllabus_total}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 2: Cursos Activos + Sparkline ── 4 cols -->
      <div
        class="col-span-12 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm md:col-span-4"
      >
        <div class="mb-5 flex items-start justify-between">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
              Este Semestre
            </p>
            <h3 class="mt-0.5 text-base font-bold text-gray-900">Cursos Activos</h3>
          </div>
          <span class="rounded-xl bg-blue-50 p-2">
            <ClipboardList size={18} class="text-blue-600" />
          </span>
        </div>

        <div class="flex items-end justify-between gap-4">
          <div>
            <p class="text-5xl font-extrabold leading-none tracking-tight text-gray-900">
              {stats.cursos_activos}
            </p>
            <div class="mt-2 flex items-center gap-1">
              <ArrowUpRight size={13} class="text-emerald-500" />
              <span class="text-xs font-medium text-emerald-600">+2 vs semestre anterior</span>
            </div>
          </div>

          <!-- Sparkline -->
          <div class="flex flex-shrink-0 flex-col items-end gap-1">
            <svg
              width={SPARK_W}
              height={SPARK_H + 6}
              viewBox="0 0 {SPARK_W} {SPARK_H + 6}"
              aria-label="Tendencia cursos activos"
            >
              <polyline
                points={spark.polyline}
                fill="none"
                stroke="#3B82F6"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              {#each spark.dots as dot}
                <circle
                  cx={dot.x}
                  cy={dot.y}
                  r={dot.isLast ? 3 : 1.5}
                  fill={dot.isLast ? '#3B82F6' : '#BFDBFE'}
                />
              {/each}
            </svg>
            <p class="text-[10px] text-gray-400">Últimos 6 semestres</p>
          </div>
        </div>
      </div>

      <!-- Card 3: Estudiantes Matriculados ── 3 cols -->
      <div
        class="col-span-12 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm md:col-span-3"
      >
        <div class="mb-5 flex items-start justify-between">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
              Total Carrera
            </p>
            <h3 class="mt-0.5 text-base font-bold text-gray-900">Estudiantes</h3>
          </div>
          <span class="rounded-xl bg-violet-50 p-2">
            <GraduationCap size={18} class="text-violet-600" />
          </span>
        </div>

        <p class="mb-5 text-5xl font-extrabold leading-none tracking-tight text-gray-900">
          {stats.estudiantes_matriculados.toLocaleString('es-CL')}
        </p>

        <!-- Estado mini-breakdown -->
        <div class="grid grid-cols-3 gap-1.5">
          <div class="rounded-xl bg-red-50 px-1 py-2 text-center">
            <p class="text-base font-bold text-red-600">{resumen_estados.no_iniciado}</p>
            <p class="mt-0.5 text-[9px] font-semibold leading-tight text-red-400">Sin inicio</p>
          </div>
          <div class="rounded-xl bg-amber-50 px-1 py-2 text-center">
            <p class="text-base font-bold text-amber-600">{resumen_estados.en_revision}</p>
            <p class="mt-0.5 text-[9px] font-semibold leading-tight text-amber-400">Revisión</p>
          </div>
          <div class="rounded-xl bg-emerald-50 px-1 py-2 text-center">
            <p class="text-base font-bold text-emerald-600">{resumen_estados.aprobado}</p>
            <p class="mt-0.5 text-[9px] font-semibold leading-tight text-emerald-400">Aprobado</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── Bottom Row ────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-12 gap-4">
      <!-- Panel: Requiere Atención ── 8 cols -->
      <div
        class="col-span-12 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm md:col-span-8"
      >
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div class="flex items-center gap-2.5">
            <div class="rounded-lg bg-red-50 p-1.5">
              <AlertTriangle size={15} class="text-red-500" />
            </div>
            <h3 class="text-sm font-bold text-gray-900">Requiere Atención</h3>
            {#if alertas.length > 0}
              <span
                class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-red-100 text-[11px] font-bold text-red-600"
                >{alertas.length}</span
              >
            {/if}
          </div>
          <Link
            href="/docente/jefe-carrera/seguimiento"
            class="flex items-center gap-0.5 text-xs font-medium text-indigo-600 transition-colors hover:text-indigo-700"
          >
            Ver todos <ChevronRight size={13} />
          </Link>
        </div>

        <div class="divide-y divide-gray-50">
          {#each alertas as alerta (alerta.id)}
            {@const s = getAlertStyle(alerta.tipo)}
            <div
              class="flex items-center gap-4 border-l-4 px-6 py-4 transition-colors hover:bg-gray-50 {s.border} {s.bg}"
            >
              <p class="flex-1 text-sm font-medium leading-snug text-gray-800">{alerta.titulo}</p>
              <button
                onclick={() => router.get(alerta.accion_url)}
                class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition-colors {s.btnCls}"
              >
                <Send size={11} />
                {alerta.accion_label}
              </button>
            </div>
          {:else}
            <div class="px-6 py-12 text-center">
              <CheckCircle2 size={32} class="mx-auto mb-2 text-emerald-400" />
              <p class="text-sm font-medium text-gray-500">Sin alertas pendientes</p>
              <p class="mt-0.5 text-xs text-gray-400">Todo está en orden este semestre</p>
            </div>
          {/each}
        </div>
      </div>

      <!-- Panel: Resumen del Semestre ── 4 cols -->
      <div
        class="col-span-12 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm md:col-span-4"
      >
        <div class="mb-5 flex items-center gap-2">
          <BarChart2 size={15} class="text-gray-400" />
          <h3 class="text-sm font-bold text-gray-900">Resumen del Semestre</h3>
        </div>

        <div class="space-y-4">
          <div>
            <div class="mb-1.5 flex justify-between text-xs">
              <span class="font-medium text-gray-600">Aprobados</span>
              <span class="font-bold text-emerald-600"
                >{resumen_estados.aprobado} / {stats.syllabus_total}</span
              >
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
              <div
                class="h-full rounded-full bg-emerald-500 transition-all duration-700"
                style="width: {stats.syllabus_total
                  ? (resumen_estados.aprobado / stats.syllabus_total) * 100
                  : 0}%"
              ></div>
            </div>
          </div>

          <div>
            <div class="mb-1.5 flex justify-between text-xs">
              <span class="font-medium text-gray-600">En Revisión</span>
              <span class="font-bold text-amber-600"
                >{resumen_estados.en_revision} / {stats.syllabus_total}</span
              >
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
              <div
                class="h-full rounded-full bg-amber-400 transition-all duration-700"
                style="width: {stats.syllabus_total
                  ? (resumen_estados.en_revision / stats.syllabus_total) * 100
                  : 0}%"
              ></div>
            </div>
          </div>

          <div>
            <div class="mb-1.5 flex justify-between text-xs">
              <span class="font-medium text-gray-600">Sin Iniciar</span>
              <span class="font-bold text-red-500"
                >{resumen_estados.no_iniciado} / {stats.syllabus_total}</span
              >
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
              <div
                class="h-full rounded-full bg-red-400 transition-all duration-700"
                style="width: {stats.syllabus_total
                  ? (resumen_estados.no_iniciado / stats.syllabus_total) * 100
                  : 0}%"
              ></div>
            </div>
          </div>
        </div>

        <div class="mt-6 border-t border-gray-100 pt-4">
          <Link
            href="/docente/jefe-carrera/seguimiento"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
          >
            <ClipboardList size={14} />
            Ver Seguimiento Completo
          </Link>
        </div>
      </div>
    </div>
  </div>
</AdminLayout>
