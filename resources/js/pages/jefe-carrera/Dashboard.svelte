<script lang="ts">
  /**
   * Dashboard ejecutivo — Jefatura de Carrera.
   *
   * Lámina «Dashboard ejecutivo»: bento de 12 columnas donde la tarjeta de
   * syllabus (5×2) ancla la lectura y el rail de alertas (4×3) corre a lo alto
   * para que la acción quede a la vista sin desplazar.
   *
   * Reglas de la lámina que no son decorativas:
   *  - La ÚNICA acción rellena de la pantalla vive en el rail de alertas. La
   *    cabecera cede la primaria: no lleva botón.
   *  - Sin alertas el rail conserva su altura con confirmación y comprobaciones
   *    del período; nunca un hueco ni una ilustración.
   *  - Escala tipográfica sin ampliar: el KPI es 28px, igual que el h1.
   *
   * Todos los datos llegan del controlador ya acotados a la carrera del jefe
   * (`JefeCarreraController::dashboard`). Los defaults son vacíos a propósito:
   * la vista no fabrica cifras de ejemplo.
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import AlertasRail from '@/components/jefe-carrera/AlertasRail.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import {
    ArrowDownRight,
    ArrowUpRight,
    BarChart2,
    BookOpen,
    CalendarCheck,
    ChevronRight,
    ClipboardList,
    GraduationCap,
    ListChecks,
    Minus,
    Users,
    UserX,
  } from 'lucide-svelte';
  import { formatFechaCorta } from '@/utils/formatters';

  // ─── Tipos ───────────────────────────────────────────────────────────────

  interface Alerta {
    id: number;
    tipo: 'critica' | 'advertencia' | 'info';
    icono?: string | null;
    titulo: string;
    count: number;
    accion_label: string;
    accion_url: string;
  }

  interface Props {
    carrera?: { nombre: string; semestre: string; ano: number };
    periodo?: {
      inicio: string | null;
      plazo_syllabus: string | null;
      ultimo_aprobado: string | null;
      anterior: { label: string; cursos: number } | null;
    };
    stats?: {
      syllabus_entregados: number;
      syllabus_total: number;
      cursos_activos: number;
      cursos_con_docente: number;
      estudiantes_matriculados: number;
    };
    resumen_estados?: {
      no_iniciado: number;
      borrador: number;
      en_revision: number;
      aprobado: number;
    };
    alertas?: Alerta[];
    metricas_resumen?: {
      asistencia_promedio: number | null;
      avance_evaluacion: number | null;
      alumnos_en_riesgo: number;
      carga_docente: { docentes: number; cursos: number; promedio: number };
    };
    generado_en?: string | null;
  }

  let {
    carrera = { nombre: 'Carrera', semestre: '', ano: 0 },
    periodo = { inicio: null, plazo_syllabus: null, ultimo_aprobado: null, anterior: null },
    stats = {
      syllabus_entregados: 0,
      syllabus_total: 0,
      cursos_activos: 0,
      cursos_con_docente: 0,
      estudiantes_matriculados: 0,
    },
    resumen_estados = { no_iniciado: 0, borrador: 0, en_revision: 0, aprobado: 0 },
    alertas = [],
    metricas_resumen = {
      asistencia_promedio: null,
      avance_evaluacion: null,
      alumnos_en_riesgo: 0,
      carga_docente: { docentes: 0, cursos: 0, promedio: 0 },
    },
    generado_en = null,
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Jefatura de Carrera', href: '/docente/jefe-carrera/dashboard' },
    { title: 'Dashboard', href: '/docente/jefe-carrera/dashboard' },
  ];

  // ─── Cabecera ────────────────────────────────────────────────────────────

  const periodoLabel = $derived(
    carrera.semestre && carrera.ano ? `${carrera.semestre} semestre ${carrera.ano}` : '',
  );

  const subtitulo = $derived(
    [
      periodoLabel,
      periodo.inicio ? `período vigente desde ${formatFechaCorta(periodo.inicio)}` : '',
    ]
      .filter(Boolean)
      .join(' · '),
  );

  // ─── Donut de syllabus ───────────────────────────────────────────────────
  // Los cortes salen de los conteos reales; las cuatro cubetas cubren el total
  // del período (BORRADOR incluido), así que el donut siempre cuadra.

  const SEGMENTOS = [
    { key: 'aprobado', label: 'Aprobado', color: '#059669' },
    { key: 'en_revision', label: 'En revisión', color: '#D97706' },
    { key: 'borrador', label: 'Borrador', color: '#A8A29E' },
    { key: 'no_iniciado', label: 'No iniciado', color: '#64748B' },
  ] as const;

  const leyenda = $derived(
    SEGMENTOS.map((s) => ({ ...s, n: resumen_estados[s.key] ?? 0 })),
  );

  const pct = $derived(
    stats.syllabus_total > 0
      ? Math.round((stats.syllabus_entregados / stats.syllabus_total) * 100)
      : 0,
  );

  const donutGradient = $derived.by(() => {
    const total = stats.syllabus_total;
    if (total <= 0) return '';
    let acc = 0;
    const cortes = leyenda.map((s) => {
      const desde = (acc / total) * 100;
      acc += s.n;
      const hasta = (acc / total) * 100;
      return `${s.color} ${desde.toFixed(2)}% ${hasta.toFixed(2)}%`;
    });
    return `conic-gradient(${cortes.join(', ')})`;
  });

  /**
   * Pie de la tarjeta de syllabus. El plazo sólo existe si TODOS los cursos del
   * período comparten la misma `fecha_limite_entrega_syllabus`; cuando ya está
   * todo aprobado, el dato que importa es la última aprobación.
   */
  const pieSyllabus = $derived.by(() => {
    const todoAprobado = stats.syllabus_total > 0 && pct === 100;
    if (todoAprobado && periodo.ultimo_aprobado) {
      return `Último aprobado: ${formatFechaCorta(periodo.ultimo_aprobado)}`;
    }
    if (periodo.plazo_syllabus) {
      return `Plazo de entrega: ${formatFechaCorta(periodo.plazo_syllabus)}`;
    }
    if (periodo.ultimo_aprobado) {
      return `Último aprobado: ${formatFechaCorta(periodo.ultimo_aprobado)}`;
    }
    return '';
  });

  // ─── Cursos activos: delta contra el período anterior ────────────────────

  const anterior = $derived(periodo.anterior);
  const delta = $derived(anterior ? stats.cursos_activos - anterior.cursos : 0);

  // ─── Rail de alertas: estado «todo al día» ───────────────────────────────

  const comprobaciones = $derived(
    stats.syllabus_total > 0
      ? [
          {
            label: 'Syllabus aprobados',
            hecho: resumen_estados.aprobado,
            total: stats.syllabus_total,
          },
          {
            label: 'Cursos con docente titular',
            hecho: stats.cursos_con_docente,
            total: stats.syllabus_total,
          },
        ]
      : [],
  );

  /** Sólo afirma lo que los datos sostienen: nunca «todo aprobado» si hay borradores. */
  const confirmacion = $derived.by(() => {
    if (stats.syllabus_total === 0) {
      return 'Ninguna alerta activa: todavía no hay cursos en el período vigente.';
    }
    if (resumen_estados.aprobado === stats.syllabus_total) {
      return `Ninguna alerta activa en la carrera. Los ${stats.syllabus_total} syllabus del período están aprobados y no hay cursos sin iniciar.`;
    }
    return `Ninguna alerta activa en la carrera. Ningún curso del período está sin syllabus iniciado ni pendiente de tu revisión.`;
  });

  // ─── Resumen de métricas ─────────────────────────────────────────────────

  /** Umbral de lectura de las barras: verde va bien, ámbar va atrasado. */
  const UMBRAL_OK = 75;

  const fmtPct = (v: number) => `${Math.round(v)}%`;
  const fmtDecimal = (v: number) =>
    v.toLocaleString('es-CL', { maximumFractionDigits: 1 });

  const pctRiesgo = $derived(
    stats.estudiantes_matriculados > 0
      ? (metricas_resumen.alumnos_en_riesgo / stats.estudiantes_matriculados) * 100
      : null,
  );

  // ─── Lenguaje visual ─────────────────────────────────────────────────────

  const CARD =
    'rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,.08)]';
  const BTN_GHOST =
    'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[13px] font-semibold text-[#002F6C] no-underline transition-colors hover:bg-[#F5F1EA]';
  const TILE =
    'flex flex-col rounded-[10px] border border-[#E5E7EB] p-3.5 no-underline transition-colors hover:bg-[#F5F1EA]';
  const KPI = 'text-[28px] font-semibold leading-none tracking-[-0.01em] text-[#1A1A24]';
</script>

<AdminLayout {breadcrumbs}>
  <!-- ─── Cabecera: sin acción primaria, se la queda el rail de alertas ──── -->
  <div class="mb-4 flex min-w-0 flex-col gap-1">
    <div class="flex items-center gap-1.5 text-[12px] text-[#5A5E6E]">
      <span>Jefatura de Carrera</span>
      <ChevronRight class="h-3 w-3" aria-hidden="true" />
      <span class="font-medium text-[#1A1A24]">Dashboard</span>
    </div>
    <h1 class="m-0 text-[28px] font-semibold tracking-[-0.01em] text-[#1A1A24]">
      Jefatura de Carrera — {carrera.nombre}
    </h1>
    {#if subtitulo}
      <span class="text-[14px] text-[#5A5E6E]">{subtitulo}</span>
    {/if}
  </div>

  <!-- ─── Bento de 12 columnas ───────────────────────────────────────────── -->
  <div class="grid grid-cols-1 items-stretch gap-4 lg:grid-cols-12">
    <!-- 1. Syllabus entregados (5×2) -->
    <section class="{CARD} flex flex-col gap-4 lg:col-span-5 lg:row-span-2">
      <div class="flex flex-wrap items-center gap-2">
        <ClipboardList class="h-4 w-4 text-[#5A5E6E]" aria-hidden="true" />
        <h2 class="m-0 text-base font-semibold text-[#1A1A24]">Syllabus entregados</h2>
        <span class="ml-auto text-[12px] text-[#5A5E6E]">
          {stats.cursos_activos} curso{stats.cursos_activos === 1 ? '' : 's'} ofertado{stats.cursos_activos ===
          1
            ? ''
            : 's'}
        </span>
      </div>

      {#if stats.syllabus_total > 0}
        <div class="flex flex-wrap items-center gap-6">
          <div
            class="flex h-[132px] w-[132px] shrink-0 items-center justify-center rounded-full"
            style="background: {donutGradient}"
            role="img"
            aria-label="{stats.syllabus_entregados} de {stats.syllabus_total} syllabus aprobados ({pct}%)"
          >
            <div class="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-white">
              <span class="text-[26px] font-semibold leading-none text-[#1A1A24]">
                {stats.syllabus_entregados}
              </span>
              <span class="mt-1 text-[12px] text-[#5A5E6E]">de {stats.syllabus_total}</span>
            </div>
          </div>

          <div class="flex min-w-[180px] flex-1 flex-col gap-3">
            <div class="flex flex-col gap-0.5">
              <span class={KPI}>{pct}%</span>
              <span class="text-[12px] text-[#5A5E6E]">de avance del período</span>
            </div>
            <div class="flex flex-col gap-2">
              {#each leyenda as s (s.key)}
                <div class="flex items-center gap-2 text-[13px]">
                  <span
                    class="h-2 w-2 shrink-0 rounded-full"
                    style="background: {s.color}"
                    aria-hidden="true"
                  ></span>
                  <span class="flex-1 {s.n === 0 ? 'text-[#5A5E6E]' : 'text-[#1A1A24]'}">
                    {s.label}
                  </span>
                  <span
                    class="font-semibold tabular-nums {s.n === 0
                      ? 'text-[#5A5E6E]'
                      : 'text-[#1A1A24]'}"
                  >
                    {s.n}
                  </span>
                </div>
              {/each}
            </div>
          </div>
        </div>
      {:else}
        <div
          class="flex flex-1 flex-col items-center justify-center gap-1.5 rounded-[10px] border border-dashed border-[#D6D9E0] bg-[#F5F1EA] px-4 py-8 text-center"
        >
          <span class="text-[14px] font-semibold text-[#1A1A24]">Sin cursos en el período</span>
          <span class="max-w-[320px] text-[13px] text-[#5A5E6E]">
            No hay cursos vigentes en la carrera, así que todavía no hay syllabus que seguir.
          </span>
        </div>
      {/if}

      <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-[#E5E7EB] pt-3">
        {#if pieSyllabus}
          <span class="text-[12px] text-[#5A5E6E]">{pieSyllabus}</span>
        {/if}
        <Link href="/docente/jefe-carrera/seguimiento" class="{BTN_GHOST} ml-auto">
          Ver seguimiento
          <ArrowUpRight class="h-3.5 w-3.5" aria-hidden="true" />
        </Link>
      </div>
    </section>

    <!-- 2. Cursos activos (3) -->
    <section class="{CARD} flex flex-col gap-2 lg:col-span-3">
      <div class="flex items-center gap-2">
        <BookOpen class="h-4 w-4 text-[#5A5E6E]" aria-hidden="true" />
        <span class="text-[12px] text-[#5A5E6E]">Cursos activos</span>
      </div>
      <span class={KPI}>{stats.cursos_activos}</span>
      {#if anterior}
        <div class="mt-auto flex flex-wrap items-center gap-1.5 pt-2">
          {#if delta > 0}
            <ArrowUpRight class="h-3.5 w-3.5 text-[#059669]" aria-hidden="true" />
            <span class="text-[12px] font-semibold text-[#047857]">+{delta}</span>
          {:else if delta < 0}
            <ArrowDownRight class="h-3.5 w-3.5 text-[#DC2626]" aria-hidden="true" />
            <span class="text-[12px] font-semibold text-[#B91C1C]">{delta}</span>
          {:else}
            <Minus class="h-3.5 w-3.5 text-[#5A5E6E]" aria-hidden="true" />
            <span class="text-[12px] font-semibold text-[#5A5E6E]">=</span>
          {/if}
          <span class="text-[12px] text-[#5A5E6E]">
            vs. {anterior.label} ({anterior.cursos})
          </span>
        </div>
      {/if}
    </section>

    <!-- 4. Alertas (4×3) — rail alto a la derecha -->
    <div class="lg:col-span-4 lg:row-span-3">
      <AlertasRail {alertas} {comprobaciones} {confirmacion} {generado_en} />
    </div>

    <!-- 3. Estudiantes matriculados (3) -->
    <section class="{CARD} flex flex-col gap-2 lg:col-span-3">
      <div class="flex items-center gap-2">
        <Users class="h-4 w-4 text-[#5A5E6E]" aria-hidden="true" />
        <span class="text-[12px] text-[#5A5E6E]">Estudiantes matriculados</span>
      </div>
      <span class={KPI}>{stats.estudiantes_matriculados.toLocaleString('es-CL')}</span>
      <span class="mt-auto pt-2 text-[12px] text-[#5A5E6E]">Matrícula vigente del período</span>
    </section>

    <!-- 5. Resumen de métricas (8) -->
    <section class="{CARD} flex flex-col gap-4 lg:col-span-8">
      <div class="flex flex-wrap items-center gap-2">
        <BarChart2 class="h-4 w-4 text-[#5A5E6E]" aria-hidden="true" />
        <h2 class="m-0 text-base font-semibold text-[#1A1A24]">Resumen de métricas</h2>
        <Link href="/docente/jefe-carrera/metricas" class="{BTN_GHOST} ml-auto">
          Abrir Métricas
          <ArrowUpRight class="h-3.5 w-3.5" aria-hidden="true" />
        </Link>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <!-- Asistencia promedio -->
        <Link href="/docente/jefe-carrera/metricas" class={TILE}>
          <div class="flex items-center gap-2">
            <CalendarCheck class="h-[15px] w-[15px] text-[#5A5E6E]" aria-hidden="true" />
            <span class="text-[12px] text-[#5A5E6E]">Asistencia promedio</span>
            <ArrowUpRight class="ml-auto h-[13px] w-[13px] text-[#5A5E6E]" aria-hidden="true" />
          </div>
          {#if metricas_resumen.asistencia_promedio !== null}
            <span class="{KPI} mt-2.5">{fmtPct(metricas_resumen.asistencia_promedio)}</span>
            <div class="mt-2.5 h-1 overflow-hidden rounded-full bg-[#F1F5F9]">
              <div
                class="h-full rounded-full {metricas_resumen.asistencia_promedio >= UMBRAL_OK
                  ? 'bg-[#059669]'
                  : 'bg-[#D97706]'}"
                style="width: {Math.min(100, Math.max(0, metricas_resumen.asistencia_promedio))}%"
              ></div>
            </div>
          {:else}
            <span class="{KPI} mt-2.5 text-[#98A0AE]">—</span>
            <span class="mt-2.5 text-[12px] text-[#5A5E6E]">Sin asistencia registrada</span>
          {/if}
        </Link>

        <!-- Avance de evaluación -->
        <Link href="/docente/jefe-carrera/metricas" class={TILE}>
          <div class="flex items-center gap-2">
            <ListChecks class="h-[15px] w-[15px] text-[#5A5E6E]" aria-hidden="true" />
            <span class="text-[12px] text-[#5A5E6E]">Avance de evaluación</span>
            <ArrowUpRight class="ml-auto h-[13px] w-[13px] text-[#5A5E6E]" aria-hidden="true" />
          </div>
          {#if metricas_resumen.avance_evaluacion !== null}
            <span class="{KPI} mt-2.5">{fmtPct(metricas_resumen.avance_evaluacion)}</span>
            <div class="mt-2.5 h-1 overflow-hidden rounded-full bg-[#F1F5F9]">
              <div
                class="h-full rounded-full {metricas_resumen.avance_evaluacion >= UMBRAL_OK
                  ? 'bg-[#059669]'
                  : 'bg-[#D97706]'}"
                style="width: {Math.min(100, Math.max(0, metricas_resumen.avance_evaluacion))}%"
              ></div>
            </div>
          {:else}
            <span class="{KPI} mt-2.5 text-[#98A0AE]">—</span>
            <span class="mt-2.5 text-[12px] text-[#5A5E6E]">Sin actividades evaluables</span>
          {/if}
        </Link>

        <!-- Alumnos en riesgo -->
        <Link href="/docente/jefe-carrera/metricas" class={TILE}>
          <div class="flex items-center gap-2">
            <UserX class="h-[15px] w-[15px] text-[#5A5E6E]" aria-hidden="true" />
            <span class="text-[12px] text-[#5A5E6E]">Alumnos en riesgo</span>
            <ArrowUpRight class="ml-auto h-[13px] w-[13px] text-[#5A5E6E]" aria-hidden="true" />
          </div>
          <span class="{KPI} mt-2.5">{metricas_resumen.alumnos_en_riesgo}</span>
          <span class="mt-2.5 text-[12px] text-[#5A5E6E]">
            {#if pctRiesgo !== null}
              {fmtDecimal(pctRiesgo)}% de {stats.estudiantes_matriculados.toLocaleString('es-CL')}
              matriculados
            {:else}
              Promedio parcial bajo 4,0
            {/if}
          </span>
        </Link>

        <!-- Carga docente promedio -->
        <Link href="/docente/jefe-carrera/metricas" class={TILE}>
          <div class="flex items-center gap-2">
            <GraduationCap class="h-[15px] w-[15px] text-[#5A5E6E]" aria-hidden="true" />
            <span class="text-[12px] text-[#5A5E6E]">Carga docente promedio</span>
            <ArrowUpRight class="ml-auto h-[13px] w-[13px] text-[#5A5E6E]" aria-hidden="true" />
          </div>
          {#if metricas_resumen.carga_docente.docentes > 0}
            <span class="{KPI} mt-2.5">{fmtDecimal(metricas_resumen.carga_docente.promedio)}</span>
            <span class="mt-2.5 text-[12px] text-[#5A5E6E]">
              cursos por docente · {metricas_resumen.carga_docente.docentes} titulares
            </span>
          {:else}
            <span class="{KPI} mt-2.5 text-[#98A0AE]">—</span>
            <span class="mt-2.5 text-[12px] text-[#5A5E6E]">Sin docentes titulares</span>
          {/if}
        </Link>
      </div>
    </section>
  </div>
</AdminLayout>
