<script lang="ts">
  /**
   * Actividades Por Estado — Docente Titular.
   *
   * Componente de presentación pura que clasifica un array de actividades
   * en tres columnas de estado:
   *
   *  • En Progreso  — Publicadas (visible) con fecha límite futura.
   *  • Pendientes   — No publicadas (!visible), pendientes de lanzar.
   *  • Vencidas     — Publicadas (visible) con fecha límite pasada.
   *
   * Cada tarjeta muestra metadatos de la actividad y enlaza a la página
   * de evaluación/calificación correspondiente.
   *
   * Props:
   *  - actividades : ActividadExtendida[] — Lista de actividades del curso.
   *  - idCurso     : number               — ID del curso para construir rutas.
   */
  import { Link } from '@inertiajs/svelte';
  import {
    Clock,
    AlertCircle,
    CheckCircle2,
    Calendar,
    Users,
    User,
    ExternalLink,
    ClipboardList,
  } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { formatFechaCorta } from '@/utils/formatters';
  // ── Props ─────────────────────────────────────────────────────────────────

  interface Props {
    actividades: Actividad[];
    idCurso: number;
  }

  let { actividades, idCurso }: Props = $props();
  // ── Classification ────────────────────────────────────────────────────────

  const now = new Date();

  const enProgreso = $derived(
    actividades.filter((a) => a.visible && new Date(a.fecha_limite) > now),
  );
  const pendientes = $derived(actividades.filter((a) => !a.visible));
  const vencidas = $derived(
    actividades.filter((a) => a.visible && new Date(a.fecha_limite) <= now),
  );

  // ── Helpers ───────────────────────────────────────────────────────────────

  /** Days remaining (positive) or overdue (negative) relative to now. */
  function daysRelative(d: string): number {
    const diff = new Date(d).getTime() - now.getTime();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
  }

  function daysLabel(d: string): string {
    const days = daysRelative(d);
    if (days === 0) return 'Vence hoy';
    if (days === 1) return 'Vence mañana';
    if (days > 1) return `${days} días restantes`;
    return `Hace ${Math.abs(days)} día${Math.abs(days) !== 1 ? 's' : ''}`;
  }

  function componenteLabel(act: Actividad): string {
    return act.componente?.tipo_componente?.tipo ?? '—';
  }

  function tipoActividadLabel(tipo: string): string {
    return tipo === 'SUMATIVA' ? 'Sumativa' : 'Formativa';
  }

  function tipoActividadClass(tipo: string): string {
    return tipo === 'SUMATIVA'
      ? 'bg-violet-100 text-violet-700 border-violet-200'
      : 'bg-sky-100 text-sky-700 border-sky-200';
  }

  // Column config for DRY rendering
  const COLUMNS = $derived([
    {
      id: 'en-progreso' as const,
      label: 'En Progreso',
      count: enProgreso.length,
      items: enProgreso,
      Icon: Clock,
      accent: {
        header: 'bg-emerald-50 border-emerald-200',
        dot: 'bg-emerald-500',
        badge: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        icon: 'text-emerald-600',
        daysText: 'text-emerald-600',
        card: 'hover:border-emerald-300',
      },
    },
    {
      id: 'pendientes' as const,
      label: 'Pendientes',
      count: pendientes.length,
      items: pendientes,
      Icon: AlertCircle,
      accent: {
        header: 'bg-amber-50 border-amber-200',
        dot: 'bg-amber-500',
        badge: 'bg-amber-100 text-amber-800 border-amber-200',
        icon: 'text-amber-500',
        daysText: 'text-amber-600',
        card: 'hover:border-amber-300',
      },
    },
    {
      id: 'vencidas' as const,
      label: 'Vencidas',
      count: vencidas.length,
      items: vencidas,
      Icon: CheckCircle2,
      accent: {
        header: 'bg-red-50 border-red-200',
        dot: 'bg-red-400',
        badge: 'bg-red-100 text-red-800 border-red-200',
        icon: 'text-red-400',
        daysText: 'text-red-500',
        card: 'hover:border-red-300',
      },
    },
  ]);
</script>

<!-- ── Three-column Kanban layout ──────────────────────────────────────────── -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
  {#each COLUMNS as col (col.id)}
    <section class="flex flex-col gap-3">
      <!-- Column header -->
      <div
        class="flex items-center justify-between px-4 py-3 rounded-xl border {col.accent.header}"
      >
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full {col.accent.dot} shrink-0"></span>
          <h3 class="text-sm font-bold text-slate-700">{col.label}</h3>
        </div>
        <span class="text-xs font-bold px-2 py-0.5 rounded-full border {col.accent.badge}">
          {col.count}
        </span>
      </div>

      <!-- Activity cards -->
      {#if col.items.length === 0}
        <div
          class="flex flex-col items-center gap-2 py-10 text-center text-slate-400 bg-slate-50 rounded-xl border border-slate-100"
        >
          <ClipboardList size={28} class="opacity-30" />
          <p class="text-xs">Sin actividades en esta categoría</p>
        </div>
      {:else}
        {#each col.items as act (act.id_actividad)}
          <div
            class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm transition-all {col
              .accent.card}"
          >
            <!-- Activity name + type badge -->
            <div class="flex items-start justify-between gap-2 mb-3">
              <p class="font-semibold text-slate-800 text-sm leading-snug flex-1 min-w-0 pr-1">
                {act.nombre}
              </p>
              <span
                class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 shrink-0"
              >
                {#if act.es_grupal}
                  <Users size={9} />
                  Grupal
                {:else}
                  <User size={9} />
                  Individual
                {/if}
              </span>
            </div>

            <!-- Meta row -->
            <div class="flex flex-col gap-1.5 text-xs text-slate-500 mb-4">
              <!-- Deadline -->
              {#if col.id !== 'pendientes'}
                <span class="flex items-center gap-1.5">
                  <Calendar size={11} class="shrink-0" />
                  {formatFechaCorta(act.fecha_limite)}
                  <span class="font-semibold {col.accent.daysText}">
                    · {daysLabel(act.fecha_limite)}
                  </span>
                </span>
              {/if}

              <!-- Tipo de actividad -->
              <span
                class="self-start inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border {tipoActividadClass(
                  act.tipo_actividad,
                )}"
              >
                {tipoActividadLabel(act.tipo_actividad)}
              </span>

              <!-- Component -->
              <span class="flex items-center gap-1.5">
                <col.Icon size={11} class="{col.accent.icon} shrink-0" />
                {componenteLabel(act)}
              </span>

              <!-- Pending visibility note -->
              {#if col.id === 'pendientes'}
                <span class="inline-flex items-center gap-1 text-amber-600 font-medium">
                  <AlertCircle size={11} />
                  No publicada para estudiantes
                </span>
              {/if}
            </div>

            <!-- CTA -->
            <Link
              href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/evaluacion"
              class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors no-underline"
            >
              <ExternalLink size={12} />
              Ver evaluación
            </Link>
          </div>
        {/each}
      {/if}
    </section>
  {/each}
</div>
