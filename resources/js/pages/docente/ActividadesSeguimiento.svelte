<script lang="ts">
  /**
   * Seguimiento de Actividades — Vista de Docente Titular.
   *
   * Página Inertia que presenta las actividades de un curso organizadas
   * en tres categorías de estado:
   *
   *  • En Progreso  — Publicadas con fecha límite futura.
   *  • Pendientes   — Aún no publicadas para estudiantes.
   *  • Vencidas     — Publicadas con fecha límite ya transcurrida.
   *
   * Incluye:
   *  - Tarjetas de resumen (métricas rápidas)
   *  - Filtro por texto y por categoría de estado
   *  - Panel kanban via ActividadesPorEstado
   *
   * Authorization:
   *  - Requiere `curso.es_titular_curso === true` o permiso `cursos:*`.
   *  - El backend protege la ruta con middleware `is_docente` y la policy
   *    correspondiente al curso.
   *
   * Props (Inertia via HandleInertiaRequests):
   *  - curso      : Datos del curso + flag es_titular_curso + userPermissions
   *  - actividades: Lista de actividades eager-loaded con componente y unidad
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { Link } from '@inertiajs/svelte';
  import {
    ArrowLeft,
    ClipboardList,
    Clock,
    AlertCircle,
    CheckCircle2,
    Crown,
    ShieldOff,
    Search,
    X,
    Plus,
  } from 'lucide-svelte';
  import ActividadesPorEstado from './components/ActividadesPorEstado.svelte';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';
  import type { Actividad } from '@/types/actividad';

  // ── Extended activity type (componente eager-loaded) ──────────────────────

  interface ActividadExtendida extends Actividad {
    componente?: {
      id_componente: number;
      tipo_componente?: { nombre: string };
    } | null;
  }

  // ── Curso type ────────────────────────────────────────────────────────────

  interface Curso {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    es_titular_curso: boolean;
    asignatura: {
      nombre: string;
      cod_asignatura: string;
    };
    userPermissions?: Permission[];
  }

  interface Props {
    curso: Curso;
    actividades: ActividadExtendida[];
  }

  // ── Props ─────────────────────────────────────────────────────────────────

  let { curso, actividades }: Props = $props();

  // ── Authorization ─────────────────────────────────────────────────────────

  const isTitular = $derived(
    curso.es_titular_curso || hasPermission(curso.userPermissions ?? [], 'cursos:*'),
  );

  // ── Filter state ──────────────────────────────────────────────────────────

  let query = $state('');
  type EstadoFiltro = 'todas' | 'en-progreso' | 'pendientes' | 'vencidas';
  let estadoFiltro = $state<EstadoFiltro>('todas');

  // ── Time reference (stable per render) ───────────────────────────────────

  const now = new Date();

  // ── Derived classifications (base, before text filter) ───────────────────

  const totalEnProgreso = $derived(
    actividades.filter((a) => a.visible && new Date(a.fecha_limite) > now).length,
  );
  const totalPendientes = $derived(actividades.filter((a) => !a.visible).length);
  const totalVencidas = $derived(
    actividades.filter((a) => a.visible && new Date(a.fecha_limite) <= now).length,
  );

  // ── Filtered list passed to ActividadesPorEstado ──────────────────────────

  const actividadesFiltradas = $derived(
    actividades.filter((a) => {
      // Text filter
      const q = query.toLowerCase();
      if (q && !a.nombre.toLowerCase().includes(q)) return false;

      // State filter
      if (estadoFiltro === 'en-progreso') return a.visible && new Date(a.fecha_limite) > now;
      if (estadoFiltro === 'pendientes') return !a.visible;
      if (estadoFiltro === 'vencidas') return a.visible && new Date(a.fecha_limite) <= now;
      return true;
    }),
  );

  // ── Summary metric cards config ───────────────────────────────────────────

  const METRIC_CARDS = $derived([
    {
      id: 'total' as const,
      label: 'Total',
      value: actividades.length,
      icon: ClipboardList,
      color: 'from-indigo-50 to-indigo-100 border-indigo-200',
      iconBg: 'bg-indigo-600',
      textColor: 'text-indigo-900',
      subColor: 'text-indigo-400',
    },
    {
      id: 'en-progreso' as const,
      label: 'En Progreso',
      value: totalEnProgreso,
      icon: Clock,
      color: 'from-emerald-50 to-emerald-100 border-emerald-200',
      iconBg: 'bg-emerald-600',
      textColor: 'text-emerald-900',
      subColor: 'text-emerald-400',
    },
    {
      id: 'pendientes' as const,
      label: 'Pendientes',
      value: totalPendientes,
      icon: AlertCircle,
      color: 'from-amber-50 to-amber-100 border-amber-200',
      iconBg: 'bg-amber-500',
      textColor: 'text-amber-900',
      subColor: 'text-amber-400',
    },
    {
      id: 'vencidas' as const,
      label: 'Vencidas',
      value: totalVencidas,
      icon: CheckCircle2,
      color: 'from-red-50 to-red-100 border-red-200',
      iconBg: 'bg-red-500',
      textColor: 'text-red-900',
      subColor: 'text-red-400',
    },
  ]);

  const ESTADO_FILTERS: { id: EstadoFiltro; label: string }[] = [
    { id: 'todas', label: 'Todas' },
    { id: 'en-progreso', label: 'En Progreso' },
    { id: 'pendientes', label: 'Pendientes' },
    { id: 'vencidas', label: 'Vencidas' },
  ];
</script>

<DocenteLayout>
  <!-- ── Access guard ─────────────────────────────────────────────────────── -->
  {#if !isTitular}
    <div class="flex flex-col items-center justify-center gap-4 h-64 text-center px-6">
      <ShieldOff size={40} class="text-red-400" />
      <div>
        <h2 class="text-lg font-bold text-slate-800">Acceso restringido</h2>
        <p class="text-sm text-slate-500 mt-1">
          Esta vista es exclusiva del Docente Titular del curso.
        </p>
      </div>
      <Link
        href="/docente/cursos/{curso.id_curso}"
        class="text-indigo-600 text-sm font-medium hover:underline"
      >
        Volver al curso
      </Link>
    </div>
  {:else}
    <!-- ── Page header ───────────────────────────────────────────────────── -->
    <div class="bg-white border-b border-slate-200 px-6 py-5">
      <nav class="flex items-center gap-1.5 text-xs text-slate-500 mb-3" aria-label="Navegación">
        <Link href="/docente/cursos" class="hover:text-indigo-600 transition-colors">
          Mis Cursos
        </Link>
        <span class="text-slate-300" aria-hidden="true">/</span>
        <Link
          href="/docente/cursos/{curso.id_curso}"
          class="hover:text-indigo-600 transition-colors"
        >
          {curso.cod_curso}
        </Link>
        <span class="text-slate-300" aria-hidden="true">/</span>
        <span class="text-slate-700 font-medium" aria-current="page">Seguimiento</span>
      </nav>

      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
              Seguimiento de Actividades
            </h1>
            <span
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold border"
              style="background:#FFF8EC; color:#D97706; border-color:#FDE68A;"
            >
              <Crown size={10} />
              Titular
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            {curso.asignatura.cod_asignatura} · {curso.asignatura.nombre}
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <Link
            href="/docente/cursos/{curso.id_curso}/actividades"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors no-underline"
          >
            <Plus size={13} />
            Gestionar actividades
          </Link>

          <Link
            href="/docente/cursos/{curso.id_curso}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-indigo-600 transition-colors no-underline"
          >
            <ArrowLeft size={14} />
            Volver
          </Link>
        </div>
      </div>
    </div>

    <!-- ── Summary metric cards ──────────────────────────────────────────── -->
    <div class="px-6 py-5 grid grid-cols-2 lg:grid-cols-4 gap-4">
      {#each METRIC_CARDS as card (card.id)}
        {@const Icon = card.icon}
        <button
          onclick={() => (estadoFiltro = card.id === 'total' ? 'todas' : (card.id as EstadoFiltro))}
          class="flex items-center gap-3 px-4 py-3.5 rounded-xl bg-gradient-to-br {card.color} border
            transition-all text-left
            {estadoFiltro === (card.id === 'total' ? 'todas' : card.id)
            ? 'ring-2 ring-indigo-500 ring-offset-1'
            : 'hover:-translate-y-0.5 hover:shadow-md'}"
          title="Filtrar por {card.label}"
        >
          <div
            class="w-9 h-9 rounded-lg {card.iconBg} text-white flex items-center justify-center shrink-0"
          >
            <Icon size={17} />
          </div>
          <div class="flex flex-col min-w-0">
            <span class="text-xl font-extrabold {card.textColor} leading-none">
              {card.value}
            </span>
            <span class="text-xs font-semibold {card.subColor} uppercase tracking-wide mt-0.5">
              {card.label}
            </span>
          </div>
        </button>
      {/each}
    </div>

    <!-- ── Filters toolbar ───────────────────────────────────────────────── -->
    <div class="px-6 py-3 flex flex-wrap items-center gap-3 bg-white border-y border-slate-100">
      <!-- Text search -->
      <div class="relative flex-1 min-w-[200px] max-w-xs">
        <Search
          size={13}
          class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
        />
        <input
          type="text"
          bind:value={query}
          placeholder="Buscar actividad…"
          class="w-full pl-8 pr-8 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder:text-slate-400 bg-white"
        />
        {#if query}
          <button
            onclick={() => (query = '')}
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700"
            aria-label="Limpiar búsqueda"
          >
            <X size={13} />
          </button>
        {/if}
      </div>

      <!-- Estado pill filters -->
      <div class="flex gap-1.5 flex-wrap">
        {#each ESTADO_FILTERS as f (f.id)}
          <button
            onclick={() => (estadoFiltro = f.id)}
            class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors
              {estadoFiltro === f.id
              ? 'bg-indigo-600 border-indigo-600 text-white'
              : 'bg-white border-slate-200 text-slate-500 hover:border-indigo-300 hover:text-indigo-600'}"
          >
            {f.label}
          </button>
        {/each}
      </div>

      <span class="text-xs text-slate-400 ml-auto shrink-0">
        {actividadesFiltradas.length} actividad{actividadesFiltradas.length !== 1 ? 'es' : ''}
      </span>
    </div>

    <!-- ── Kanban panel ──────────────────────────────────────────────────── -->
    <div class="px-6 py-5">
      {#if actividades.length === 0}
        <div
          class="flex flex-col items-center gap-3 py-20 text-center text-slate-400 bg-slate-50 rounded-xl border border-slate-100"
        >
          <ClipboardList size={40} class="opacity-30" />
          <p class="text-sm font-medium">No hay actividades creadas para este curso.</p>
          <Link
            href="/docente/cursos/{curso.id_curso}/actividades"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-colors no-underline"
          >
            <Plus size={13} />
            Crear primera actividad
          </Link>
        </div>
      {:else}
        <ActividadesPorEstado actividades={actividadesFiltradas} idCurso={curso.id_curso} />
      {/if}
    </div>
  {/if}
</DocenteLayout>
