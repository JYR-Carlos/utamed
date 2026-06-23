<script lang="ts">
  /**
   * Mi Carrera — Jefe de Carrera (solo lectura)
   *
   * Vista de detalle de la carrera del jefe: datos generales, planes de estudio
   * y métricas estructurales (planes, asignaturas en malla, cursos).
   * No permite edición; la gestión se hace desde Malla/Planes y Asignaturas.
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import {
    ArrowLeft,
    GraduationCap,
    ClipboardList,
    BookOpen,
    Layers,
    MapPin,
    Clock,
    ChevronRight,
    Inbox,
  } from 'lucide-svelte';

  // ─── Types ───────────────────────────────────────────────────────────────────

  interface PlanItem {
    id_plan: number;
    agno_plan?: number | string | null;
    version_plan?: number | string | null;
    creditos_sct_totales?: number | null;
  }

  interface Props {
    carrera?: {
      nombre: string;
      jornada?: string | null;
      sede?: string | null;
      id_carrera?: number;
    };
    planes?: PlanItem[];
    stats?: { planes: number; asignaturas: number; cursos: number };
  }

  let {
    carrera = { nombre: 'Mi Carrera' },
    planes = [],
    stats = { planes: 0, asignaturas: 0, cursos: 0 },
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Jefe de Carrera', href: '/docente/jefe-carrera/dashboard' },
    { title: 'Mi Carrera', href: '/docente/jefe-carrera/carrera' },
  ];

  const statCards = $derived([
    { label: 'Planes de Estudio', value: stats.planes, icon: ClipboardList, tone: 'indigo' },
    { label: 'Asignaturas en Malla', value: stats.asignaturas, icon: BookOpen, tone: 'emerald' },
    { label: 'Cursos', value: stats.cursos, icon: Layers, tone: 'violet' },
  ]);

  const toneCls: Record<string, string> = {
    indigo: 'bg-indigo-50 text-indigo-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    violet: 'bg-violet-50 text-violet-600',
  };
</script>

<AdminLayout {breadcrumbs}>
  <div class="min-h-screen px-6 py-8">
    <!-- ─── Page Header ───────────────────────────────────────────────────── -->
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
          Jefe de Carrera
        </p>
        <h1 class="text-2xl font-bold text-gray-900">{carrera.nombre}</h1>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500">
          {#if carrera.jornada}
            <span class="inline-flex items-center gap-1.5">
              <Clock size={14} class="text-gray-400" />
              {carrera.jornada}
            </span>
          {/if}
          {#if carrera.sede}
            <span class="inline-flex items-center gap-1.5">
              <MapPin size={14} class="text-gray-400" />
              {carrera.sede}
            </span>
          {/if}
        </div>
      </div>

      <span class="rounded-2xl bg-indigo-50 p-3">
        <GraduationCap size={24} class="text-indigo-600" />
      </span>
    </div>

    <!-- ─── Stat Cards ────────────────────────────────────────────────────── -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
      {#each statCards as card (card.label)}
        {@const Icon = card.icon}
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
          <div class="mb-4 flex items-start justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
              {card.label}
            </p>
            <span class="rounded-xl p-2 {toneCls[card.tone]}">
              <Icon size={18} />
            </span>
          </div>
          <p class="text-4xl font-extrabold leading-none tracking-tight text-gray-900">
            {card.value.toLocaleString('es-CL')}
          </p>
        </div>
      {/each}
    </div>

    <!-- ─── Planes de Estudio ─────────────────────────────────────────────── -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
        <div class="flex items-center gap-2.5">
          <div class="rounded-lg bg-indigo-50 p-1.5">
            <ClipboardList size={15} class="text-indigo-500" />
          </div>
          <h3 class="text-sm font-bold text-gray-900">Planes de Estudio</h3>
          {#if planes.length > 0}
            <span
              class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-indigo-100 px-1.5 text-[11px] font-bold text-indigo-600"
              >{planes.length}</span
            >
          {/if}
        </div>
        <Link
          href="/docente/jefe-carrera/planes"
          class="flex items-center gap-0.5 text-xs font-medium text-indigo-600 transition-colors hover:text-indigo-700"
        >
          Gestionar malla <ChevronRight size={13} />
        </Link>
      </div>

      {#if planes.length === 0}
        <div class="px-6 py-12 text-center">
          <Inbox size={32} class="mx-auto mb-2 text-gray-200" />
          <p class="text-sm font-medium text-gray-500">Sin planes de estudio</p>
          <p class="mt-0.5 text-xs text-gray-400">Crea el primer plan desde Malla / Planes</p>
        </div>
      {:else}
        <div class="divide-y divide-gray-50">
          {#each planes as plan (plan.id_plan)}
            <Link
              href="/docente/jefe-carrera/planes/{plan.id_plan}/asignaturas"
              class="flex items-center justify-between px-6 py-4 transition-colors hover:bg-gray-50"
            >
              <div class="flex items-center gap-3">
                <span
                  class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 text-slate-400"
                >
                  <ClipboardList size={16} />
                </span>
                <div>
                  <p class="text-sm font-semibold text-gray-800">
                    Plan {plan.agno_plan ?? '—'}
                    {#if plan.version_plan != null}
                      <span class="text-gray-400">· v{plan.version_plan}</span>
                    {/if}
                  </p>
                  {#if plan.creditos_sct_totales != null}
                    <p class="mt-0.5 text-xs text-gray-400">
                      {plan.creditos_sct_totales} créditos SCT
                    </p>
                  {/if}
                </div>
              </div>
              <ChevronRight size={16} class="text-gray-300" />
            </Link>
          {/each}
        </div>
      {/if}
    </div>
  </div>
</AdminLayout>
