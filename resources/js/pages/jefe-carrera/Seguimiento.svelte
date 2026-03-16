<script lang="ts">
  /**
   * Seguimiento Operativo de Cursos y Syllabus — Jefe de Carrera
   *
   * Pantalla 2: Data grid con filtros contextuales y badges de estado.
   * Pantalla 3 (embed): Slide-over de previsualización de syllabus con
   *   sticky footer ejecutivo (Aprobar / Solicitar Cambios).
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link, router } from '@inertiajs/svelte';
  import {
    Search,
    X,
    Filter,
    Eye,
    Check,
    XCircle,
    MessageSquare,
    MoreVertical,
    ArrowLeft,
    Calendar,
    FileText,
    BookOpen,
    CheckCircle,
    Info,
  } from 'lucide-svelte';
  import { untrack } from 'svelte';
  import { Input } from '@/components/ui/input';
  import { Button } from '@/components/ui/button';
  import { Label } from '@/components/ui/label';

  // ─── Types ───────────────────────────────────────────────────────────────────

  type EstadoSyllabus = 'NO_INICIADO' | 'BORRADOR' | 'EN_REVISION' | 'APROBADO' | 'RECHAZADO';

  interface Docente {
    nombre: string;
    inicial: string;
    color: string;
  }

  interface CursoSeguimiento {
    id_curso: number;
    cod_asignatura: string;
    nombre_asignatura: string;
    seccion: string;
    docente: Docente;
    estado_syllabus: EstadoSyllabus;
    fecha_actualizacion: string | null;
    completud: number;
    id_programa: number | null;
  }

  interface SyllabusData {
    titulo: string;
    codigo: string;
    docente: string;
    descripcion: string;
    objetivos: string[];
    unidades: { numero: number; titulo: string; horas: number; contenidos: string[] }[];
    evaluaciones: { descripcion: string; ponderacion: string; semana: number | string }[];
    completud: number;
  }

  interface Props {
    cursos?: CursoSeguimiento[];
    semestres_disponibles?: string[];
    agnos_disponibles?: number[];
    filters?: { q?: string; semestre?: string; agno?: string; estado?: string };
    pagination?: { current_page: number; last_page: number; total: number };
    carrera?: { nombre: string };
  }

  let {
    cursos = mockCursos(),
    semestres_disponibles = ['Primero', 'Segundo'],
    agnos_disponibles = [2026, 2025, 2024],
    filters = {},
    pagination = { current_page: 1, last_page: 1, total: 8 },
    carrera = { nombre: 'Diseño Multimedia' },
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Jefe de Carrera', href: '/docente/jefe-carrera/dashboard' },
    { title: 'Seguimiento Operativo', href: '/docente/jefe-carrera/seguimiento' },
  ];

  // ─── Filter state ────────────────────────────────────────────────────────────

  // untrack: capture the prop's initial value only; state is managed locally after that
  let searchQ = $state(untrack(() => filters.q ?? ''));
  let filtroSemestre = $state(untrack(() => filters.semestre ?? ''));
  let filtroAgno = $state(untrack(() => filters.agno ?? ''));
  let filtroEstado = $state(untrack(() => filters.estado ?? ''));
  let openKebab = $state<number | null>(null);

  const hasFilters = $derived(!!(searchQ || filtroSemestre || filtroAgno || filtroEstado));

  function aplicarFiltros() {
    const params: Record<string, string> = {};
    if (searchQ) params.q = searchQ;
    if (filtroSemestre) params.semestre = filtroSemestre;
    if (filtroAgno) params.agno = filtroAgno;
    if (filtroEstado) params.estado = filtroEstado;
    router.get('/docente/jefe-carrera/seguimiento', params, { preserveState: true });
  }

  function limpiarFiltros() {
    searchQ = '';
    filtroSemestre = '';
    filtroAgno = '';
    filtroEstado = '';
    router.get('/docente/jefe-carrera/seguimiento', {});
  }

  // ─── Status badge config ─────────────────────────────────────────────────────

  const estadoConfig: Record<EstadoSyllabus, { label: string; cls: string; dot: string }> = {
    NO_INICIADO: {
      label: 'No Iniciado',
      cls: 'bg-red-100 text-red-700 border-red-200',
      dot: 'bg-red-500',
    },
    BORRADOR: {
      label: 'Borrador',
      cls: 'bg-gray-100 text-gray-600 border-gray-200',
      dot: 'bg-gray-400',
    },
    EN_REVISION: {
      label: 'En Revisión',
      cls: 'bg-amber-100 text-amber-700 border-amber-200',
      dot: 'bg-amber-500',
    },
    APROBADO: {
      label: 'Aprobado',
      cls: 'bg-emerald-100 text-emerald-700 border-emerald-200',
      dot: 'bg-emerald-500',
    },
    RECHAZADO: {
      label: 'Rechazado',
      cls: 'bg-red-100 text-red-700 border-red-200',
      dot: 'bg-red-500',
    },
  };

  function formatFecha(iso: string | null): string {
    if (!iso) return '—';
    const [year, month, day] = iso.slice(0, 10).split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('es-CL', {
      day: '2-digit',
      month: 'short',
      year: '2-digit',
    });
  }

  // ─── Slide-over (Pantalla 3) ─────────────────────────────────────────────────

  interface SlideOverState {
    isOpen: boolean;
    curso: CursoSeguimiento | null;
    syllabus: SyllabusData | null;
    isLoading: boolean;
    approving: boolean;
    rejecting: boolean;
    notes: string;
  }

  let slideOver = $state<SlideOverState>({
    isOpen: false,
    curso: null,
    syllabus: null,
    isLoading: false,
    approving: false,
    rejecting: false,
    notes: '',
  });

  function abrirSlideOver(curso: CursoSeguimiento) {
    slideOver = { ...slideOver, isOpen: true, curso, isLoading: true, notes: '' };
    openKebab = null;

    if (curso.id_programa) {
      // TODO: replace with real API call via router.get or fetch
      setTimeout(() => {
        slideOver = { ...slideOver, isLoading: false, syllabus: buildMockSyllabus(curso) };
      }, 350);
    } else {
      slideOver = { ...slideOver, isLoading: false, syllabus: null };
    }
  }

  function cerrarSlideOver() {
    slideOver = { ...slideOver, isOpen: false };
  }

  function aprobarSyllabus() {
    const programa_id = slideOver.curso?.id_programa;
    if (!programa_id) return;
    slideOver = { ...slideOver, approving: true };
    router.post(
      `/docente/jefe-carrera/programas/${programa_id}/aprobar`,
      {},
      {
        onSuccess: () => cerrarSlideOver(),
        onFinish: () => {
          slideOver = { ...slideOver, approving: false };
        },
      },
    );
  }

  function solicitarCambios() {
    const programa_id = slideOver.curso?.id_programa;
    if (!programa_id) return;
    slideOver = { ...slideOver, rejecting: true };
    router.post(
      `/docente/jefe-carrera/programas/${programa_id}/rechazar`,
      { notas: slideOver.notes },
      {
        onSuccess: () => cerrarSlideOver(),
        onFinish: () => {
          slideOver = { ...slideOver, rejecting: false };
        },
      },
    );
  }

  function handleWindowKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
      if (slideOver.isOpen) cerrarSlideOver();
      else openKebab = null;
    }
  }

  // ─── Mock data helpers (demo / standalone) ───────────────────────────────────

  function mockCursos(): CursoSeguimiento[] {
    const docentes: Docente[] = [
      { nombre: 'Prof. María González', inicial: 'MG', color: '#6366F1' },
      { nombre: 'Prof. Carlos Vega', inicial: 'CV', color: '#F59E0B' },
      { nombre: 'Prof. Ana Rodríguez', inicial: 'AR', color: '#10B981' },
      { nombre: 'Prof. Luis Herrera', inicial: 'LH', color: '#3B82F6' },
      { nombre: 'Prof. Sofía Martínez', inicial: 'SM', color: '#EC4899' },
    ];
    const estados: EstadoSyllabus[] = [
      'APROBADO',
      'EN_REVISION',
      'NO_INICIADO',
      'BORRADOR',
      'RECHAZADO',
      'APROBADO',
      'EN_REVISION',
      'NO_INICIADO',
    ];
    const asignaturas = [
      ['DM-101', 'Diseño Visual Digital I'],
      ['DM-203', 'Tipografía Aplicada'],
      ['DM-215', 'Fotografía y Composición'],
      ['DM-310', 'Motion Graphics'],
      ['DM-322', 'UX/UI Design'],
      ['DM-408', 'Branding Estratégico'],
      ['DM-412', 'Producción Audiovisual'],
      ['DM-501', 'Proyecto de Título'],
    ];
    return asignaturas.map(([cod, nombre], i) => ({
      id_curso: i + 1,
      cod_asignatura: cod,
      nombre_asignatura: nombre,
      seccion: `SEC-${String(i + 1).padStart(2, '0')}`,
      docente: docentes[i % docentes.length],
      estado_syllabus: estados[i],
      fecha_actualizacion:
        estados[i] !== 'NO_INICIADO' ? `2026-03-${String(i + 1).padStart(2, '0')}` : null,
      completud:
        estados[i] === 'APROBADO'
          ? 100
          : estados[i] === 'EN_REVISION'
            ? 85
            : estados[i] === 'BORRADOR'
              ? 50
              : 0,
      id_programa: estados[i] !== 'NO_INICIADO' ? i + 100 : null,
    }));
  }

  function buildMockSyllabus(curso: CursoSeguimiento): SyllabusData {
    return {
      titulo: curso.nombre_asignatura,
      codigo: curso.cod_asignatura,
      docente: curso.docente.nombre,
      descripcion:
        'Esta asignatura desarrolla en el estudiante las competencias fundamentales del diseño comunicacional orientado a medios digitales e interactivos, abordando metodologías de trabajo y herramientas propias del campo disciplinar.',
      objetivos: [
        'Comprender los principios del diseño de interfaces digitales y su relación con la experiencia del usuario.',
        'Aplicar metodologías de diseño centrado en el usuario (DCU) en proyectos reales.',
        'Gestionar flujos de trabajo en herramientas de diseño contemporáneas (Figma, Adobe CC).',
        'Desarrollar proyectos de comunicación visual adaptados a contextos digitales específicos.',
      ],
      unidades: [
        {
          numero: 1,
          titulo: 'Fundamentos del Diseño Digital',
          horas: 12,
          contenidos: [
            'Historia y evolución del diseño digital',
            'Principios visuales aplicados a pantallas',
            'Psicología del color en medios digitales',
          ],
        },
        {
          numero: 2,
          titulo: 'Metodologías de Diseño UX',
          horas: 16,
          contenidos: [
            'Design Thinking y Double Diamond',
            'Investigación de usuarios: entrevistas y tests',
            'Prototipado de baja y alta fidelidad',
          ],
        },
        {
          numero: 3,
          titulo: 'Producción Visual Digital',
          horas: 20,
          contenidos: [
            'Diseño vectorial avanzado en Illustrator',
            'Composición y recursos para redes sociales',
            'Motion graphics básico con After Effects',
          ],
        },
      ],
      evaluaciones: [
        { descripcion: 'Proyecto parcial: Branding Digital', ponderacion: '30%', semana: 8 },
        {
          descripcion: 'Ejercicios prácticos acumulativos',
          ponderacion: '20%',
          semana: 'Continuo',
        },
        {
          descripcion: 'Proyecto final: Campaña digital integrada',
          ponderacion: '50%',
          semana: 16,
        },
      ],
      completud: curso.completud,
    };
  }
</script>

<svelte:window onkeydown={handleWindowKeydown} />

<AdminLayout {breadcrumbs}>
  <div class="min-h-screen px-6 py-8">
    <!-- ─── Page Header ───────────────────────────────────────────────────── -->
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          href="/docente/jefe-carrera/dashboard"
          class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 transition-colors hover:text-gray-700"
        >
          <ArrowLeft size={14} />
          Volver al Dashboard
        </Link>
        <h1 class="text-2xl font-bold text-gray-900">Seguimiento Operativo</h1>
        <p class="mt-0.5 text-sm text-gray-500">
          {carrera.nombre}&nbsp;&middot;&nbsp;Estado de syllabus por asignatura
        </p>
      </div>
    </div>

    <!-- ─── Filters Bar ───────────────────────────────────────────────────── -->
    <div class="mb-4 rounded-xl border border-gray-100 bg-white px-5 py-4 shadow-sm">
      <div class="flex flex-wrap items-end gap-3">
        <!-- Search -->
        <div class="min-w-[220px] flex-1">
          <Label class="mb-1 block text-xs font-medium text-gray-500"
            >Buscar asignatura o docente</Label
          >
          <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
            <Input
              bind:value={searchQ}
              placeholder="Nombre o código…"
              class="h-9 pl-9 text-sm"
              onkeydown={(e) => e.key === 'Enter' && aplicarFiltros()}
            />
          </div>
        </div>

        <!-- Año -->
        <div>
          <Label class="mb-1 block text-xs font-medium text-gray-500">Año</Label>
          <select
            bind:value={filtroAgno}
            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="">Todos</option>
            {#each agnos_disponibles as a (a)}
              <option value={String(a)}>{a}</option>
            {/each}
          </select>
        </div>

        <!-- Semestre -->
        <div>
          <Label class="mb-1 block text-xs font-medium text-gray-500">Semestre</Label>
          <select
            bind:value={filtroSemestre}
            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="">Todos</option>
            {#each semestres_disponibles as s (s)}
              <option value={s}>{s}</option>
            {/each}
          </select>
        </div>

        <!-- Estado -->
        <div>
          <Label class="mb-1 block text-xs font-medium text-gray-500">Estado</Label>
          <select
            bind:value={filtroEstado}
            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="">Todos los estados</option>
            <option value="NO_INICIADO">No Iniciado</option>
            <option value="BORRADOR">Borrador</option>
            <option value="EN_REVISION">En Revisión</option>
            <option value="APROBADO">Aprobado</option>
            <option value="RECHAZADO">Rechazado</option>
          </select>
        </div>

        <Button onclick={aplicarFiltros} size="sm" class="h-9 px-4">
          <Filter size={13} class="mr-1.5" />
          Filtrar
        </Button>

        {#if hasFilters}
          <button
            onclick={limpiarFiltros}
            class="flex h-9 items-center gap-1 px-3 text-sm text-gray-500 transition-colors hover:text-gray-700"
          >
            <X size={13} />
            Limpiar
          </button>
        {/if}
      </div>
    </div>

    <!-- Result count -->
    <p class="mb-3 text-xs font-medium text-gray-400">
      {pagination.total} asignatura{pagination.total !== 1 ? 's' : ''} encontrada{pagination.total !==
      1
        ? 's'
        : ''}
    </p>

    <!-- ─── Data Grid ─────────────────────────────────────────────────────── -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100">
              <th
                class="py-3.5 pl-6 pr-4 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400"
                >Asignatura / Código</th
              >
              <th
                class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400"
                >Docente Asignado</th
              >
              <th
                class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400"
                >Estado del Syllabus</th
              >
              <th
                class="px-4 py-3.5 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400"
                >Última Actualización</th
              >
              <th
                class="py-3.5 pl-4 pr-6 text-center text-[11px] font-semibold uppercase tracking-wider text-gray-400"
                >Acciones</th
              >
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            {#if cursos.length === 0}
              <tr>
                <td colspan="5" class="py-20 text-center">
                  <FileText size={36} class="mx-auto mb-3 text-gray-200" />
                  <p class="text-sm font-medium text-gray-400">No se encontraron asignaturas</p>
                  <p class="mt-1 text-xs text-gray-300">Ajusta los filtros de búsqueda</p>
                </td>
              </tr>
            {/if}

            {#each cursos as curso (curso.id_curso)}
              {@const badge = estadoConfig[curso.estado_syllabus] ?? estadoConfig.NO_INICIADO}
              <tr class="transition-colors hover:bg-gray-50/60">
                <!-- Asignatura / Código -->
                <td class="py-4 pl-6 pr-4">
                  <p class="font-semibold leading-tight text-gray-900">{curso.nombre_asignatura}</p>
                  <p class="mt-0.5 font-mono text-xs text-gray-400">{curso.cod_asignatura}</p>
                </td>

                <!-- Docente -->
                <td class="px-4 py-4">
                  <div class="flex items-center gap-2.5">
                    <div
                      class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                      style="background-color: {curso.docente.color}"
                      aria-hidden="true"
                    >
                      {curso.docente.inicial}
                    </div>
                    <span class="text-sm leading-tight text-gray-700">{curso.docente.nombre}</span>
                  </div>
                </td>

                <!-- Estado del Syllabus (CRÍTICO) -->
                <td class="px-4 py-4">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {badge.cls}"
                  >
                    <span class="h-1.5 w-1.5 rounded-full {badge.dot}"></span>
                    {badge.label}
                  </span>
                  {#if curso.estado_syllabus !== 'NO_INICIADO'}
                    <div class="mt-1.5 flex max-w-[90px] items-center gap-1.5">
                      <div class="h-1 flex-1 overflow-hidden rounded-full bg-gray-100">
                        <div
                          class="h-full rounded-full transition-all {curso.completud >= 80
                            ? 'bg-emerald-500'
                            : curso.completud >= 50
                              ? 'bg-amber-400'
                              : 'bg-red-400'}"
                          style="width: {curso.completud}%"
                        ></div>
                      </div>
                      <span class="flex-shrink-0 text-[10px] text-gray-400">{curso.completud}%</span
                      >
                    </div>
                  {/if}
                </td>

                <!-- Última Actualización -->
                <td class="px-4 py-4">
                  <div class="flex items-center gap-1.5">
                    {#if curso.fecha_actualizacion}
                      <Calendar size={11} class="text-gray-300" />
                    {/if}
                    <span class="text-xs text-gray-500"
                      >{formatFecha(curso.fecha_actualizacion)}</span
                    >
                  </div>
                </td>

                <!-- Acciones -->
                <td class="py-4 pl-4 pr-6">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Ver Syllabus -->
                    <button
                      onclick={() => abrirSlideOver(curso)}
                      class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-600 transition-colors hover:bg-indigo-100"
                    >
                      <Eye size={13} />
                      Ver
                    </button>

                    <!-- Kebab -->
                    <div class="relative">
                      <button
                        onclick={() => {
                          openKebab = openKebab === curso.id_curso ? null : curso.id_curso;
                        }}
                        class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Más opciones"
                      >
                        <MoreVertical size={15} />
                      </button>

                      {#if openKebab === curso.id_curso}
                        <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
                        <div
                          class="fixed inset-0 z-10"
                          onclick={() => (openKebab = null)}
                          role="presentation"
                        ></div>
                        <div
                          class="absolute right-0 top-full z-20 mt-1 w-48 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 shadow-lg"
                        >
                          <button
                            onclick={() => abrirSlideOver(curso)}
                            class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                          >
                            <Eye size={14} class="text-gray-400" />
                            Ver Syllabus
                          </button>
                          {#if curso.estado_syllabus === 'EN_REVISION'}
                            <button
                              onclick={() => abrirSlideOver(curso)}
                              class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-sm text-emerald-700 hover:bg-emerald-50"
                            >
                              <Check size={14} class="text-emerald-500" />
                              Aprobar
                            </button>
                            <button
                              onclick={() => abrirSlideOver(curso)}
                              class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                            >
                              <XCircle size={14} class="text-red-400" />
                              Solicitar Cambios
                            </button>
                          {/if}
                          <div class="my-1 border-t border-gray-100"></div>
                          <button
                            class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-sm text-gray-500 hover:bg-gray-50"
                          >
                            <MessageSquare size={14} class="text-gray-400" />
                            Enviar Mensaje
                          </button>
                        </div>
                      {/if}
                    </div>
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      {#if pagination.last_page > 1}
        <div class="flex items-center justify-between border-t border-gray-100 px-6 py-3.5">
          <p class="text-xs text-gray-400">{pagination.total} registros</p>
          <div class="flex items-center gap-1">
            {#each Array.from({ length: pagination.last_page }, (_, i) => i + 1) as page}
              <button
                onclick={() =>
                  router.get('/docente/jefe-carrera/seguimiento', { page: String(page) })}
                class="h-7 w-7 rounded-lg text-xs font-medium transition-colors {page ===
                pagination.current_page
                  ? 'bg-indigo-600 text-white'
                  : 'text-gray-600 hover:bg-gray-100'}"
              >
                {page}
              </button>
            {/each}
          </div>
        </div>
      {/if}
    </div>
  </div>
</AdminLayout>

<!-- ═══════════════════════════════════════════════════════════════════════════
     PANTALLA 3: Slide-over de Previsualización de Syllabus
     Renderizado fuera del AdminLayout para apilamiento z-index correcto.
════════════════════════════════════════════════════════════════════════════ -->

<!-- Backdrop -->
{#if slideOver.isOpen}
  <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
  <div
    class="fixed inset-0 z-40 bg-gray-900/40 backdrop-blur-[2px]"
    onclick={cerrarSlideOver}
    role="presentation"
  ></div>
{/if}

<!-- Panel — 50% viewport width, slides from right -->
<div
  class="fixed right-0 top-0 z-50 flex h-full w-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-in-out md:w-1/2 {slideOver.isOpen
    ? 'translate-x-0'
    : 'translate-x-full'}"
  role="dialog"
  aria-modal="true"
  aria-label="Previsualización de Syllabus"
>
  {#if slideOver.isOpen && slideOver.curso}
    {@const curso = slideOver.curso}
    {@const badge = estadoConfig[curso.estado_syllabus] ?? estadoConfig.NO_INICIADO}

    <!-- ── Header ── -->
    <div class="flex-shrink-0 border-b border-gray-100 px-8 pb-5 pt-7">
      <div class="flex items-start justify-between">
        <div>
          <div class="mb-1 flex items-center gap-2">
            <BookOpen size={15} class="text-indigo-500" />
            <span class="text-[11px] font-semibold uppercase tracking-widest text-indigo-600">
              Syllabus
            </span>
          </div>
          <h2 class="text-xl font-bold leading-tight text-gray-900">
            {curso.nombre_asignatura}
          </h2>
          <p class="mt-0.5 text-sm text-gray-500">
            {curso.cod_asignatura}&nbsp;&middot;&nbsp;{curso.docente.nombre}
          </p>
        </div>
        <button
          onclick={cerrarSlideOver}
          class="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
          aria-label="Cerrar panel"
        >
          <X size={20} />
        </button>
      </div>

      <div class="mt-3 flex items-center gap-3">
        <span
          class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {badge.cls}"
        >
          <span class="h-1.5 w-1.5 rounded-full {badge.dot}"></span>
          {badge.label}
        </span>
        {#if curso.fecha_actualizacion}
          <span class="flex items-center gap-1 text-xs text-gray-400">
            <Calendar size={11} />
            Actualizado: {formatFecha(curso.fecha_actualizacion)}
          </span>
        {/if}
      </div>
    </div>

    <!-- ── Scrollable content ── -->
    <div class="flex-1 overflow-y-auto px-8 py-6">
      {#if slideOver.isLoading}
        <!-- Skeleton -->
        <div class="space-y-6 animate-pulse">
          {#each [1, 2, 3] as _}
            <div>
              <div class="mb-3 h-2.5 w-1/4 rounded-full bg-gray-100"></div>
              <div class="h-3 w-full rounded bg-gray-50"></div>
              <div class="mt-1.5 h-3 w-5/6 rounded bg-gray-50"></div>
              <div class="mt-1.5 h-3 w-4/6 rounded bg-gray-50"></div>
            </div>
          {/each}
        </div>
      {:else if !slideOver.syllabus}
        <!-- No syllabus -->
        <div class="py-20 text-center">
          <FileText size={40} class="mx-auto mb-3 text-gray-200" />
          <p class="text-sm font-medium text-gray-500">Este curso no tiene un syllabus creado</p>
          <p class="mt-1 text-xs text-gray-400">El docente aún no ha iniciado el documento</p>
        </div>
      {:else}
        {@const s = slideOver.syllabus}

        <!-- Descripción -->
        <section class="mb-8">
          <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-gray-400">
            Descripción del Curso
          </h3>
          <p class="text-sm leading-relaxed text-gray-700">{s.descripcion}</p>
        </section>

        <!-- Objetivos de Aprendizaje -->
        <section class="mb-8">
          <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-gray-400">
            Objetivos de Aprendizaje
          </h3>
          <ul class="space-y-2.5">
            {#each s.objetivos as obj, i}
              <li class="flex items-start gap-3 text-sm text-gray-700">
                <span
                  class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-600"
                  >{i + 1}</span
                >
                <span class="leading-relaxed">{obj}</span>
              </li>
            {/each}
          </ul>
        </section>

        <!-- Unidades Temáticas -->
        <section class="mb-8">
          <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-gray-400">
            Unidades Temáticas
          </h3>
          <div class="space-y-3">
            {#each s.unidades as unidad}
              <div class="rounded-xl border border-gray-100 p-4">
                <div class="mb-2.5 flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span
                      class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 text-[11px] font-bold text-indigo-600"
                      >U{unidad.numero}</span
                    >
                    <h4 class="text-sm font-semibold text-gray-800">{unidad.titulo}</h4>
                  </div>
                  <span
                    class="rounded-full bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-400"
                    >{unidad.horas}h</span
                  >
                </div>
                <ul class="ml-8 space-y-1">
                  {#each unidad.contenidos as contenido}
                    <li class="flex items-center gap-1.5 text-xs text-gray-600">
                      <span class="h-1 w-1 flex-shrink-0 rounded-full bg-gray-300"></span>
                      {contenido}
                    </li>
                  {/each}
                </ul>
              </div>
            {/each}
          </div>
        </section>

        <!-- Sistema de Evaluación -->
        <section class="mb-8">
          <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-gray-400">
            Sistema de Evaluación
          </h3>
          <div class="overflow-hidden rounded-xl border border-gray-100">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500"
                    >Evaluación</th
                  >
                  <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500"
                    >Ponderación</th
                  >
                  <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500">Semana</th
                  >
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                {#each s.evaluaciones as ev}
                  <tr>
                    <td class="px-4 py-2.5 text-xs text-gray-700">{ev.descripcion}</td>
                    <td class="px-4 py-2.5 text-center">
                      <span class="text-xs font-bold text-indigo-600">{ev.ponderacion}</span>
                    </td>
                    <td class="px-4 py-2.5 text-center text-xs text-gray-500">
                      Sem. {ev.semana}
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </section>

        <!-- Notas de revisión (solo en estado EN_REVISION) -->
        {#if curso.estado_syllabus === 'EN_REVISION'}
          <section class="mb-6">
            <label
              for="notas-revision"
              class="mb-2 block text-[11px] font-semibold uppercase tracking-widest text-gray-400"
            >
              Notas de Revisión
            </label>
            <textarea
              id="notas-revision"
              bind:value={slideOver.notes}
              placeholder="Escribe los comentarios para solicitar cambios…"
              rows={3}
              class="w-full resize-none rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            ></textarea>
          </section>
        {/if}
      {/if}
    </div>

    <!-- ── Sticky Footer ── -->
    <div class="flex-shrink-0 border-t border-gray-100 bg-white px-8 py-4">
      <div class="flex items-center justify-between gap-3">
        <!-- Left: completud chip -->
        <div class="flex items-center gap-1.5">
          <Info size={13} class="text-gray-400" />
          {#if slideOver.syllabus}
            <span class="text-xs text-gray-400">
              Completud:
              <span class="font-semibold text-gray-600">{slideOver.syllabus.completud}%</span>
            </span>
          {:else}
            <span class="text-xs text-gray-300">Sin documento</span>
          {/if}
        </div>

        <!-- Right: action buttons -->
        <div class="flex items-center gap-2">
          <button
            onclick={cerrarSlideOver}
            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100"
          >
            Cerrar
          </button>

          {#if slideOver.syllabus && curso.estado_syllabus === 'EN_REVISION'}
            <button
              onclick={solicitarCambios}
              disabled={slideOver.rejecting}
              class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-700 transition-colors hover:bg-amber-200 disabled:opacity-60"
            >
              <MessageSquare size={14} />
              {slideOver.rejecting ? 'Enviando…' : 'Solicitar Cambios'}
            </button>
            <button
              onclick={aprobarSyllabus}
              disabled={slideOver.approving}
              class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-emerald-700 disabled:opacity-60"
            >
              <CheckCircle size={14} />
              {slideOver.approving ? 'Aprobando…' : 'Aprobar Documento'}
            </button>
          {:else if slideOver.syllabus && curso.estado_syllabus === 'APROBADO'}
            <span
              class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700"
            >
              <CheckCircle size={14} />
              Documento Aprobado
            </span>
          {/if}
        </div>
      </div>
    </div>
  {/if}
</div>
