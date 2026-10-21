<script lang="ts">
  /**
   * Seguimiento de syllabus — Jefatura de Carrera.
   *
   * Lámina «Seguimiento de syllabus»: data grid con filtros sobre un campo
   * derivado, slide-over de previsualización con índice romano y footer de
   * decisión persistente.
   *
   * Dos reglas mandan sobre el resto:
   *  - Las acciones por fila obedecen al ESTADO. Aprobar y rechazar sólo
   *    existen en «En revisión»; «No iniciado» no tiene previsualización porque
   *    no hay documento. Nada se dibuja en gris deshabilitado: lo que no se
   *    permite, no se dibuja.
   *  - El vacío nombra el filtro responsable y ofrece la salida; nunca dice
   *    sólo «sin resultados».
   *
   * Todos los datos llegan del controlador ya acotados a la carrera del jefe.
   * La vista no fabrica filas de ejemplo.
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { router } from '@inertiajs/svelte';
  import {
    Search,
    X,
    XCircle,
    Eye,
    Check,
    MoreHorizontal,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    MessageSquare,
    Send,
    FileText,
  } from 'lucide-svelte';
  import { untrack } from 'svelte';
  import { formatFechaCorta } from '@/utils/formatters';

  // ─── Tipos ───────────────────────────────────────────────────────────────

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
    letra_grupo: string | null;
    fecha_limite_syllabus: string | null;
    docente: Docente;
    estado_syllabus: EstadoSyllabus;
    fecha_actualizacion: string | null;
    completud: number;
    id_programa: number | null;
  }

  interface SyllabusData {
    titulo: string;
    codigo: string;
    letra_grupo: string | null;
    agno_real: number | null;
    semestre_real: number | null;
    docente: string;
    descripcion: string;
    objetivos: string[];
    unidades: { numero: number; titulo: string; horas: number; contenidos: string[] }[];
    evaluaciones: { descripcion: string; ponderacion: string; semana: number | string }[];
    version: number | null;
    autor: string | null;
    ultima_accion: string | null;
    completud: number;
  }

  interface Props {
    cursos?: CursoSeguimiento[];
    semestres_disponibles?: string[];
    agnos_disponibles?: number[];
    plazo_syllabus?: string | null;
    filters?: { q?: string; semestre?: string; agno?: string; estado?: string };
    pagination?: {
      current_page: number;
      last_page: number;
      total: number;
      total_carrera?: number;
    };
    carrera?: { nombre: string };
  }

  let {
    cursos = [],
    semestres_disponibles = [],
    agnos_disponibles = [],
    plazo_syllabus = null,
    filters = {},
    pagination = { current_page: 1, last_page: 1, total: 0, total_carrera: 0 },
    carrera = { nombre: 'Mi Carrera' },
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Jefatura de Carrera', href: '/docente/jefe-carrera/dashboard' },
    { title: 'Seguimiento', href: '/docente/jefe-carrera/seguimiento' },
  ];

  // ─── Filtros ─────────────────────────────────────────────────────────────

  // untrack: captura el valor inicial del prop; a partir de ahí manda el estado local.
  let searchQ = $state(untrack(() => filters.q ?? ''));
  let filtroSemestre = $state(untrack(() => filters.semestre ?? ''));
  let filtroAgno = $state(untrack(() => filters.agno ?? ''));
  let filtroEstado = $state(untrack(() => filters.estado ?? ''));
  let openKebab = $state<number | null>(null);

  const hasFilters = $derived(!!(searchQ || filtroSemestre || filtroAgno || filtroEstado));
  const totalCarrera = $derived(pagination.total_carrera ?? pagination.total);

  /** Rango mostrado en el pie de la tabla (el controlador pagina de 10 en 10). */
  const POR_PAGINA = 10;
  const rangoDesde = $derived((pagination.current_page - 1) * POR_PAGINA + 1);
  const rangoHasta = $derived(Math.min(pagination.current_page * POR_PAGINA, pagination.total));

  const ESTADO_LABEL: Record<EstadoSyllabus, string> = {
    NO_INICIADO: 'No iniciado',
    BORRADOR: 'Borrador',
    EN_REVISION: 'En revisión',
    APROBADO: 'Aprobado',
    RECHAZADO: 'Rechazado',
  };

  function navegar(params: Record<string, string>) {
    router.get('/docente/jefe-carrera/seguimiento', params, { preserveState: true });
  }

  function aplicarFiltros(page?: number) {
    const params: Record<string, string> = {};
    if (searchQ) params.q = searchQ;
    if (filtroSemestre) params.semestre = filtroSemestre;
    if (filtroAgno) params.agno = filtroAgno;
    if (filtroEstado) params.estado = filtroEstado;
    if (page && page > 1) params.page = String(page);
    navegar(params);
  }

  function limpiarFiltros() {
    searchQ = '';
    filtroSemestre = '';
    filtroAgno = '';
    filtroEstado = '';
    navegar({});
  }

  /** Período que describe la cabecera, con lo que haya seleccionado. */
  const periodoLabel = $derived.by(() => {
    const partes: string[] = [];
    if (filtroSemestre) partes.push(`${filtroSemestre} semestre`);
    if (filtroAgno) partes.push(filtroAgno);
    return partes.join(' ');
  });

  /** Nombra el filtro culpable del vacío, para que la salida sea evidente. */
  const filtroCulpable = $derived.by(() => {
    if (filtroEstado) {
      const periodo = periodoLabel ? ` en ${periodoLabel}` : '';
      return `Ningún curso con estado «${ESTADO_LABEL[filtroEstado as EstadoSyllabus] ?? filtroEstado}»${periodo}`;
    }
    if (searchQ) return `Ningún curso coincide con «${searchQ}»`;
    if (periodoLabel) return `Ningún curso en ${periodoLabel}`;
    return 'Ningún curso coincide con los filtros';
  });

  // ─── Pastillas de estado ─────────────────────────────────────────────────

  const estadoConfig: Record<EstadoSyllabus, { label: string; cls: string; dot: string }> = {
    NO_INICIADO: {
      label: 'No iniciado',
      cls: 'border-slate-300 bg-slate-100 text-slate-600',
      dot: 'bg-slate-500',
    },
    BORRADOR: {
      label: 'Borrador',
      cls: 'border-gray-200 bg-sand text-ink-2',
      dot: 'bg-stone-400',
    },
    EN_REVISION: {
      label: 'En revisión',
      cls: 'border-amber-200 bg-amber-50 text-amber-700',
      dot: 'bg-amber-600',
    },
    APROBADO: {
      label: 'Aprobado',
      cls: 'border-emerald-200 bg-emerald-50 text-emerald-700',
      dot: 'bg-emerald-600',
    },
    RECHAZADO: {
      label: 'Rechazado',
      cls: 'border-red-200 bg-red-50 text-red-700',
      dot: 'bg-red-600',
    },
  };

  /** Hay documento que previsualizar en todo estado salvo «No iniciado». */
  const tieneDocumento = (c: CursoSeguimiento) =>
    c.estado_syllabus !== 'NO_INICIADO' && !!c.id_programa;

  /** Aprobar y rechazar sólo existen mientras el syllabus está en revisión. */
  const admiteDecision = (c: CursoSeguimiento) =>
    c.estado_syllabus === 'EN_REVISION' && !!c.id_programa;

  // ─── Slide-over de previsualización ──────────────────────────────────────

  interface SlideOverState {
    isOpen: boolean;
    curso: CursoSeguimiento | null;
    syllabus: SyllabusData | null;
    isLoading: boolean;
    approving: boolean;
  }

  let slideOver = $state<SlideOverState>({
    isOpen: false,
    curso: null,
    syllabus: null,
    isLoading: false,
    approving: false,
  });

  /** Índice del documento, construido con las secciones que TRAE el syllabus. */
  const ROMANOS = ['I.', 'II.', 'III.', 'IV.', 'V.', 'VI.', 'VII.', 'VIII.', 'IX.'];

  const secciones = $derived.by(() => {
    const s = slideOver.syllabus;
    if (!s) return [] as { id: string; titulo: string; romano: string }[];
    const presentes: { id: string; titulo: string }[] = [
      { id: 'identificacion', titulo: 'Identificación' },
    ];
    if (s.descripcion) presentes.push({ id: 'presentacion', titulo: 'Presentación' });
    if (s.objetivos.length > 0) presentes.push({ id: 'objetivos', titulo: 'Objetivos' });
    if (s.unidades.length > 0) presentes.push({ id: 'unidades', titulo: 'Unidades' });
    if (s.evaluaciones.length > 0) presentes.push({ id: 'evaluacion', titulo: 'Evaluación' });
    return presentes.map((sec, i) => ({ ...sec, romano: ROMANOS[i] ?? `${i + 1}.` }));
  });

  let seccionActiva = $state('identificacion');
  let panelDocumento = $state<HTMLElement | null>(null);

  function irASeccion(id: string) {
    seccionActiva = id;
    panelDocumento?.querySelector(`#sec-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /** Marca como activa la sección cuyo encabezado está más arriba pero visible. */
  function alDesplazar() {
    if (!panelDocumento) return;
    const tope = panelDocumento.getBoundingClientRect().top;
    let actual = secciones[0]?.id ?? '';
    for (const sec of secciones) {
      const el = panelDocumento.querySelector(`#sec-${sec.id}`);
      if (el && el.getBoundingClientRect().top - tope <= 24) actual = sec.id;
    }
    if (actual) seccionActiva = actual;
  }

  async function abrirSlideOver(curso: CursoSeguimiento) {
    slideOver = { ...slideOver, isOpen: true, curso, isLoading: true, syllabus: null };
    openKebab = null;
    seccionActiva = 'identificacion';
    cerrarSolicitud();

    if (!curso.id_programa) {
      slideOver = { ...slideOver, isLoading: false, syllabus: null };
      return;
    }

    try {
      const res = await fetch(`/docente/jefe-carrera/programas/${curso.id_programa}/preview`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      // Sólo aplica si el panel sigue abierto sobre el mismo curso.
      if (slideOver.isOpen && slideOver.curso?.id_curso === curso.id_curso) {
        slideOver = { ...slideOver, isLoading: false, syllabus: data?.syllabus ?? null };
      }
    } catch {
      if (slideOver.isOpen && slideOver.curso?.id_curso === curso.id_curso) {
        slideOver = { ...slideOver, isLoading: false, syllabus: null };
      }
    }
  }

  function cerrarSlideOver() {
    slideOver = { ...slideOver, isOpen: false };
    cerrarSolicitud();
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

  // ─── Solicitud de cambios: la razón es obligatoria ───────────────────────

  const RAZON_MAX = 500;

  let solicitudAbierta = $state(false);
  let razon = $state('');
  let enviandoSolicitud = $state(false);
  let razonError = $state<string | null>(null);
  let razonInput = $state<HTMLTextAreaElement | null>(null);

  const razonVacia = $derived(razon.trim().length === 0);

  function abrirSolicitud() {
    solicitudAbierta = true;
    razon = '';
    razonError = null;
  }

  function cerrarSolicitud() {
    solicitudAbierta = false;
    razon = '';
    razonError = null;
    enviandoSolicitud = false;
  }

  function enviarSolicitud() {
    const programa_id = slideOver.curso?.id_programa;
    if (!programa_id) return;

    // El botón nunca se dibuja gris: si falta la razón, el envío no sale y el
    // foco vuelve al campo, que ya estaba en error desde que se abrió.
    if (razonVacia) {
      razonError = 'La razón es obligatoria: se envía al docente y queda en el historial.';
      razonInput?.focus();
      return;
    }

    enviandoSolicitud = true;
    router.post(
      `/docente/jefe-carrera/programas/${programa_id}/rechazar`,
      { notas: razon.trim() },
      {
        onSuccess: () => cerrarSlideOver(),
        onError: (errors: Record<string, string>) => {
          razonError = (Object.values(errors)[0] as string) ?? 'No se pudo enviar la solicitud.';
          razonInput?.focus();
        },
        onFinish: () => {
          enviandoSolicitud = false;
        },
      },
    );
  }

  function handleWindowKeydown(e: KeyboardEvent) {
    if (e.key !== 'Escape') return;
    if (solicitudAbierta) cerrarSolicitud();
    else if (slideOver.isOpen) cerrarSlideOver();
    else openKebab = null;
  }

  // ─── Lenguaje visual ─────────────────────────────────────────────────────

  const CARD = 'rounded-xl border border-gray-200 bg-white shadow-[0_1px_3px_rgba(0,0,0,.08)]';
  const TH =
    'px-4 py-2.5 text-left text-[12px] font-semibold uppercase tracking-[0.04em] text-ink-2';
  const CAMPO =
    'h-[38px] rounded-lg border border-line bg-white text-[14px] text-ink focus:border-navy focus:outline-none';
  const ICON_BTN =
    'inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg border transition-colors';
  const BTN_OUTLINE =
    'inline-flex items-center gap-[7px] rounded-lg border border-line bg-white px-3.5 py-2.5 text-[14px] font-medium text-ink transition-colors hover:bg-sand';
  const BTN_PRIMARY =
    'inline-flex items-center gap-[7px] rounded-lg border border-navy bg-navy px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-navy-2 disabled:opacity-60';
</script>

<svelte:window onkeydown={handleWindowKeydown} />

<AdminLayout {breadcrumbs}>
  <!-- ─── Cabecera ───────────────────────────────────────────────────────── -->
  <div class="mb-4 flex flex-wrap items-start gap-6">
    <div class="flex min-w-0 flex-col gap-1">
      <div class="flex items-center gap-1.5 text-[12px] text-ink-2">
        <span>Jefatura de Carrera</span>
        <ChevronRight class="h-3 w-3" aria-hidden="true" />
        <span class="font-medium text-ink">Seguimiento</span>
      </div>
      <h1 class="m-0 text-[28px] font-semibold tracking-[-0.01em] text-ink">
        Seguimiento de syllabus — {carrera.nombre}
      </h1>
      <span class="text-[14px] text-ink-2">
        {periodoLabel || 'Todos los períodos'}{plazo_syllabus
          ? ` · plazo de entrega ${formatFechaCorta(plazo_syllabus)}`
          : ''}
      </span>
    </div>
  </div>

  <!-- ─── Barra de filtros ───────────────────────────────────────────────── -->
  <div class="{CARD} mb-4 flex flex-wrap items-center gap-2.5 px-4 py-3.5">
    <!-- Búsqueda -->
    <div class="relative flex min-w-[260px] flex-1 items-center">
      <Search class="pointer-events-none absolute left-[11px] h-[15px] w-[15px] text-ink-2" />
      <input
        bind:value={searchQ}
        placeholder="Buscar asignatura o docente…"
        aria-label="Buscar asignatura o docente"
        class="{CAMPO} w-full px-[34px]"
        onkeydown={(e) => e.key === 'Enter' && aplicarFiltros()}
      />
      {#if searchQ}
        <button
          onclick={() => {
            searchQ = '';
            aplicarFiltros();
          }}
          class="absolute right-[11px] text-ink-2 hover:text-ink"
          aria-label="Limpiar búsqueda"
        >
          <X class="h-[15px] w-[15px]" />
        </button>
      {/if}
    </div>

    <!-- Semestre: conmutador segmentado -->
    <div class="flex flex-none gap-[3px] rounded-lg bg-slate-100 p-[3px]">
      {#each semestres_disponibles as s (s)}
        {@const activo = filtroSemestre === s}
        <button
          onclick={() => {
            filtroSemestre = activo ? '' : s;
            aplicarFiltros();
          }}
          aria-pressed={activo}
          class="flex h-8 items-center rounded-md px-3 text-[13px] transition-colors {activo
            ? 'bg-white font-semibold text-navy shadow-[0_1px_2px_rgba(0,0,0,.06)]'
            : 'font-medium text-ink-2 hover:text-ink'}"
        >
          {s}
        </button>
      {/each}
    </div>

    <!-- Año -->
    <div class="relative flex-none">
      <select
        bind:value={filtroAgno}
        onchange={() => aplicarFiltros()}
        aria-label="Filtrar por año"
        class="{CAMPO} appearance-none py-0 pl-3 pr-8"
      >
        <option value="">Año: todos</option>
        {#each agnos_disponibles as a (a)}
          <option value={String(a)}>Año {a}</option>
        {/each}
      </select>
      <ChevronDown
        class="pointer-events-none absolute right-[11px] top-3 h-3.5 w-3.5 text-ink-2"
      />
    </div>

    <!-- Estado: se resalta cuando hay filtro activo -->
    <div class="relative flex-none">
      <select
        bind:value={filtroEstado}
        onchange={() => aplicarFiltros()}
        aria-label="Filtrar por estado del syllabus"
        class="h-[38px] appearance-none rounded-lg border bg-white py-0 pl-3 pr-8 text-[14px] focus:outline-none {filtroEstado
          ? 'border-navy font-medium text-navy'
          : 'border-line text-ink focus:border-navy'}"
      >
        <option value="">Estado: todos</option>
        <option value="NO_INICIADO">No iniciado</option>
        <option value="BORRADOR">Borrador</option>
        <option value="EN_REVISION">En revisión</option>
        <option value="APROBADO">Aprobado</option>
        <option value="RECHAZADO">Rechazado</option>
      </select>
      <ChevronDown
        class="pointer-events-none absolute right-[11px] top-3 h-3.5 w-3.5 {filtroEstado
          ? 'text-navy'
          : 'text-ink-2'}"
      />
    </div>

    {#if hasFilters}
      <button
        onclick={limpiarFiltros}
        class="inline-flex flex-none items-center gap-1.5 rounded-lg px-2.5 py-2 text-[13px] font-semibold text-navy transition-colors hover:bg-sand"
      >
        <X class="h-3.5 w-3.5" />
        Limpiar
      </button>
    {/if}
  </div>

  <!-- ─── Tabla ──────────────────────────────────────────────────────────── -->
  <div class="{CARD} overflow-hidden">
    <div class="flex flex-wrap items-center gap-2.5 border-b border-gray-200 px-4 py-3">
      <span class="text-[14px] font-semibold text-ink">
        {pagination.total} de {totalCarrera}
        {totalCarrera === 1 ? 'curso' : 'cursos'}
      </span>
      <span class="text-[12px] text-ink-2">
        coinciden con los filtros · el estado es un campo derivado, el total varía al filtrar
      </span>
    </div>

    {#if cursos.length === 0}
      <!-- El vacío nombra el filtro responsable y ofrece la salida -->
      <div class="flex flex-col items-center gap-3 px-8 py-11 text-center">
        {#if hasFilters}
          <div
            class="h-20 w-[120px] rounded-lg border border-gray-200"
            style="background:repeating-linear-gradient(135deg,var(--background-color-sand, #F5F1EA) 0 8px,var(--background-color-stone-200, #EFE9DF) 8px 16px)"
            aria-hidden="true"
          ></div>
          <span class="text-[16px] font-semibold text-ink">{filtroCulpable}</span>
          <p class="m-0 max-w-[420px] text-[14px] text-ink-2">
            {#if totalCarrera > 0}
              Los {totalCarrera}
              {totalCarrera === 1 ? 'curso' : 'cursos'} de la carrera siguen ahí; sólo la combinación
              de filtros deja la tabla sin filas.
            {:else}
              La carrera todavía no tiene cursos registrados.
            {/if}
          </p>
          {#if totalCarrera > 0}
            <div class="flex gap-2">
              <button onclick={limpiarFiltros} class={BTN_OUTLINE}>
                <X class="h-[15px] w-[15px] text-ink-2" />
                Limpiar filtros
              </button>
              <button onclick={limpiarFiltros} class={BTN_PRIMARY}>
                Ver los {totalCarrera} cursos
              </button>
            </div>
          {/if}
        {:else}
          <FileText class="h-9 w-9 text-slate-300" />
          <span class="text-[16px] font-semibold text-ink"
            >La carrera todavía no tiene cursos registrados</span
          >
          <p class="m-0 max-w-[420px] text-[14px] text-ink-2">
            En cuanto se creen cursos para esta carrera, aparecerán aquí con el estado de su
            syllabus.
          </p>
        {/if}
      </div>
    {:else}
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-[14px]">
          <thead class="bg-sand">
            <tr>
              <th class="{TH} w-[104px]">Código</th>
              <th class={TH}>Asignatura</th>
              <th class="{TH} w-[70px] px-2">Grupo</th>
              <th class="{TH} w-[180px]">Docente titular</th>
              <th class="{TH} w-[150px]">Actualizado</th>
              <th class="{TH} w-[150px]">Estado</th>
              <th class="w-[190px] px-4 py-2.5"><span class="sr-only">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            {#each cursos as curso, i (curso.id_curso)}
              {@const badge = estadoConfig[curso.estado_syllabus] ?? estadoConfig.NO_INICIADO}
              {@const conDoc = tieneDocumento(curso)}
              {@const conDecision = admiteDecision(curso)}
              <tr class="border-t border-gray-200 {i % 2 === 1 ? 'bg-stone-50' : ''}">
                <td class="px-4 py-2.5 font-mono text-[13px] text-ink-2"
                  >{curso.cod_asignatura}</td
                >
                <td class="px-4 py-2.5 font-medium text-ink">{curso.nombre_asignatura}</td>
                <td class="px-2 py-2.5">
                  {#if curso.letra_grupo}
                    <span
                      class="inline-flex h-[22px] w-[22px] items-center justify-center rounded-md bg-sand text-[12px] font-semibold text-ink-2"
                      >{curso.letra_grupo}</span
                    >
                  {:else}
                    <span class="text-gray-400">—</span>
                  {/if}
                </td>
                <td class="px-4 py-2.5 text-ink">{curso.docente.nombre}</td>
                <td class="px-4 py-2.5 text-ink-2">
                  {curso.fecha_actualizacion ? formatFechaCorta(curso.fecha_actualizacion) : '—'}
                </td>
                <td class="px-4 py-2.5">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-[3px] text-[12px] font-medium {badge.cls}"
                  >
                    <span class="h-1.5 w-1.5 rounded-full {badge.dot}" aria-hidden="true"></span>
                    {badge.label}
                  </span>
                </td>

                <!-- Acciones: sólo las que el estado permite -->
                <td class="px-4 py-2.5">
                  <div class="flex justify-end gap-1">
                    {#if conDoc}
                      <button
                        onclick={() => abrirSlideOver(curso)}
                        class="{ICON_BTN} border-line hover:bg-sand"
                        aria-label="Previsualizar syllabus de {curso.nombre_asignatura}"
                        title="Previsualizar"
                      >
                        <Eye class="h-[15px] w-[15px] text-navy" />
                      </button>
                    {/if}
                    {#if conDecision}
                      <button
                        onclick={() => abrirSlideOver(curso)}
                        class="{ICON_BTN} border-emerald-200 bg-emerald-50 hover:bg-emerald-100"
                        aria-label="Revisar para aprobar {curso.nombre_asignatura}"
                        title="Aprobar"
                      >
                        <Check class="h-[15px] w-[15px] text-emerald-700" />
                      </button>
                      <button
                        onclick={() => abrirSlideOver(curso)}
                        class="{ICON_BTN} border-red-200 bg-red-50 hover:bg-red-100"
                        aria-label="Revisar para solicitar cambios en {curso.nombre_asignatura}"
                        title="Solicitar cambios"
                      >
                        <XCircle class="h-[15px] w-[15px] text-red-700" />
                      </button>
                    {/if}
                    <button
                      onclick={() =>
                        router.visit(`/docente/cursos/${curso.id_curso}/mensajeria`)}
                      class="{ICON_BTN} border-line hover:bg-sand"
                      aria-label="Mensajería de {curso.nombre_asignatura}"
                      title="Mensajería del curso"
                    >
                      <MessageSquare class="h-[15px] w-[15px] text-ink-2" />
                    </button>

                    <div class="relative">
                      <button
                        onclick={() => {
                          openKebab = openKebab === curso.id_curso ? null : curso.id_curso;
                        }}
                        class="{ICON_BTN} border-transparent hover:bg-sand"
                        aria-label="Más opciones de {curso.nombre_asignatura}"
                      >
                        <MoreHorizontal class="h-[15px] w-[15px] text-ink-2" />
                      </button>

                      {#if openKebab === curso.id_curso}
                        <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
                        <div
                          class="fixed inset-0 z-10"
                          onclick={() => (openKebab = null)}
                          role="presentation"
                        ></div>
                        <div
                          class="absolute right-0 top-full z-20 mt-1 w-52 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
                        >
                          <button
                            onclick={() => router.visit(`/docente/cursos/${curso.id_curso}`)}
                            class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-[13px] text-ink hover:bg-sand"
                          >
                            <FileText class="h-3.5 w-3.5 text-ink-2" />
                            Ver el curso
                          </button>
                          {#if conDoc}
                            <button
                              onclick={() => abrirSlideOver(curso)}
                              class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-[13px] text-ink hover:bg-sand"
                            >
                              <Eye class="h-3.5 w-3.5 text-ink-2" />
                              Previsualizar syllabus
                            </button>
                          {/if}
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

      <!-- Pie: recuento + paginación -->
      <div class="flex items-center gap-2 border-t border-gray-200 px-4 py-2.5">
        <span class="mr-auto text-[12px] text-ink-2">
          Mostrando {rangoDesde}–{rangoHasta} de {pagination.total}
          {pagination.total === 1 ? 'curso filtrado' : 'cursos filtrados'}
        </span>
        {#if pagination.last_page > 1}
          <button
            onclick={() => aplicarFiltros(pagination.current_page - 1)}
            disabled={pagination.current_page <= 1}
            class="{ICON_BTN} border-line hover:bg-sand disabled:opacity-40"
            aria-label="Página anterior"
          >
            <ChevronLeft class="h-3.5 w-3.5 text-ink-2" />
          </button>
          {#each Array.from({ length: pagination.last_page }, (_, i) => i + 1) as page (page)}
            <button
              onclick={() => aplicarFiltros(page)}
              aria-current={page === pagination.current_page ? 'page' : undefined}
              class="inline-flex h-[30px] min-w-[30px] items-center justify-center rounded-lg border px-2 text-[13px] transition-colors {page ===
              pagination.current_page
                ? 'border-navy bg-navy font-semibold text-white'
                : 'border-line font-medium text-ink hover:bg-sand'}"
            >
              {page}
            </button>
          {/each}
          <button
            onclick={() => aplicarFiltros(pagination.current_page + 1)}
            disabled={pagination.current_page >= pagination.last_page}
            class="{ICON_BTN} border-line hover:bg-sand disabled:opacity-40"
            aria-label="Página siguiente"
          >
            <ChevronRight class="h-3.5 w-3.5 text-ink-2" />
          </button>
        {/if}
      </div>
    {/if}
  </div>
</AdminLayout>

<!-- ═══════════════════════════════════════════════════════════════════════
     Slide-over de previsualización. Fuera del AdminLayout para apilar bien
     el z-index. El fondo se oscurece pero deja ver la tabla: no se pierde el
     sitio en la lista.
════════════════════════════════════════════════════════════════════════ -->

{#if slideOver.isOpen}
  <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
  <div
    class="fixed inset-0 z-40 bg-[rgba(26,26,36,.45)]"
    onclick={cerrarSlideOver}
    role="presentation"
  ></div>
{/if}

<div
  class="fixed right-0 top-0 z-50 flex h-full w-full max-w-[720px] flex-col bg-white shadow-[0_20px_40px_rgba(0,0,0,.15)] transition-transform duration-300 ease-in-out {slideOver.isOpen
    ? 'translate-x-0'
    : 'translate-x-full'}"
  role="dialog"
  aria-modal="true"
  aria-label="Previsualización de syllabus"
>
  {#if slideOver.isOpen && slideOver.curso}
    {@const curso = slideOver.curso}
    {@const badge = estadoConfig[curso.estado_syllabus] ?? estadoConfig.NO_INICIADO}

    <!-- ── Cabecera ── -->
    <div class="flex flex-none items-center gap-3 border-b border-gray-200 px-5 py-4">
      <button
        onclick={cerrarSlideOver}
        class="{ICON_BTN} h-8 w-8 border-line hover:bg-sand"
        aria-label="Volver a la lista"
      >
        <ArrowLeft class="h-4 w-4 text-ink" />
      </button>
      <div class="flex min-w-0 flex-col">
        <span class="truncate font-mono text-[11.5px] text-ink-2">
          {curso.cod_asignatura}{curso.letra_grupo ? ` · Grupo ${curso.letra_grupo}` : ''}{periodoLabel
            ? ` · ${periodoLabel}`
            : ''}
        </span>
        <span class="truncate text-[16px] font-semibold leading-[1.3] text-ink"
          >{curso.nombre_asignatura}</span
        >
      </div>
      <span
        class="ml-auto inline-flex flex-none items-center gap-1.5 rounded-full border px-2.5 py-1 text-[12px] font-medium {badge.cls}"
      >
        <span class="h-1.5 w-1.5 rounded-full {badge.dot}" aria-hidden="true"></span>
        {badge.label}
      </span>
      <button
        onclick={cerrarSlideOver}
        class="inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg text-ink-2 transition-colors hover:bg-sand hover:text-ink"
        aria-label="Cerrar panel"
      >
        <X class="h-4 w-4" />
      </button>
    </div>

    <!-- ── Cuerpo: índice + documento ── -->
    <div class="flex min-h-0 flex-1">
      {#if secciones.length > 0}
        <nav
          class="hidden w-[212px] flex-none flex-col gap-0.5 overflow-y-auto border-r border-gray-200 bg-stone-50 px-3 py-3.5 sm:flex"
          aria-label="Contenido del syllabus"
        >
          <span
            class="px-2 pb-2 pt-0.5 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-ink-2"
            >Contenido</span
          >
          {#each secciones as sec (sec.id)}
            {@const activa = seccionActiva === sec.id}
            <button
              onclick={() => irASeccion(sec.id)}
              class="flex gap-2 rounded-lg px-2 py-[7px] text-left text-[12.5px] transition-colors {activa
                ? 'bg-uta-blue-light font-semibold text-navy'
                : 'text-ink hover:bg-sand'}"
            >
              <span
                class="w-[22px] flex-none font-mono {activa ? 'text-navy' : 'text-ink-2'}"
                >{sec.romano}</span
              >
              {sec.titulo}
            </button>
          {/each}

          {#if slideOver.syllabus?.ultima_accion || slideOver.syllabus?.version != null}
            <div
              class="mt-auto flex flex-col gap-1 border-t border-gray-200 pt-2.5 text-[11.5px] text-ink-2"
            >
              {#if slideOver.syllabus?.ultima_accion}
                <span>Movido {formatFechaCorta(slideOver.syllabus.ultima_accion)}</span>
              {/if}
              {#if slideOver.syllabus?.version != null}
                <span>
                  Versión {slideOver.syllabus.version}{slideOver.syllabus.autor
                    ? ` · ${slideOver.syllabus.autor}`
                    : ''}
                </span>
              {/if}
            </div>
          {/if}
        </nav>
      {/if}

      <div
        bind:this={panelDocumento}
        onscroll={alDesplazar}
        class="flex min-w-0 flex-1 flex-col gap-[18px] overflow-y-auto px-6 py-5"
      >
        {#if slideOver.isLoading}
          <div class="animate-pulse space-y-6">
            {#each [1, 2, 3] as bloque (bloque)}
              <div>
                <div class="mb-3 h-2.5 w-1/4 rounded-full bg-gray-200"></div>
                <div class="h-3 w-full rounded bg-sand"></div>
                <div class="mt-1.5 h-3 w-5/6 rounded bg-sand"></div>
                <div class="mt-1.5 h-3 w-4/6 rounded bg-sand"></div>
              </div>
            {/each}
          </div>
        {:else if !slideOver.syllabus}
          <div class="py-20 text-center">
            <FileText class="mx-auto mb-3 h-10 w-10 text-slate-300" />
            <p class="m-0 text-[14px] font-medium text-ink-2">
              Este curso no tiene un syllabus creado
            </p>
            <p class="mt-1 text-[12.5px] text-gray-400">
              El docente aún no ha iniciado el documento
            </p>
          </div>
        {:else}
          {@const s = slideOver.syllabus}
          {@const romano = (id: string) => secciones.find((x) => x.id === id)?.romano ?? ''}

          <!-- Identificación -->
          <section id="sec-identificacion" class="flex flex-col gap-2.5">
            <div class="flex items-baseline gap-2">
              <span class="font-mono text-[12px] text-ink-2">{romano('identificacion')}</span>
              <h2 class="m-0 text-[16px] font-semibold text-ink">Identificación</h2>
            </div>
            <div
              class="grid grid-cols-1 gap-x-5 gap-y-2.5 rounded-[10px] border border-gray-200 bg-stone-50 p-3.5 sm:grid-cols-2"
            >
              <div class="flex flex-col">
                <span class="text-[11.5px] text-ink-2">Asignatura</span>
                <span class="text-[13px] font-medium text-ink">{s.titulo}</span>
              </div>
              <div class="flex flex-col">
                <span class="text-[11.5px] text-ink-2">Código</span>
                <span class="font-mono text-[13px] text-ink">{s.codigo}</span>
              </div>
              {#if s.letra_grupo}
                <div class="flex flex-col">
                  <span class="text-[11.5px] text-ink-2">Grupo</span>
                  <span class="text-[13px] font-medium text-ink">{s.letra_grupo}</span>
                </div>
              {/if}
              {#if s.agno_real}
                <div class="flex flex-col">
                  <span class="text-[11.5px] text-ink-2">Período</span>
                  <span class="text-[13px] font-medium text-ink"
                    >{s.semestre_real ?? '—'}º semestre {s.agno_real}</span
                  >
                </div>
              {/if}
              <div class="flex flex-col">
                <span class="text-[11.5px] text-ink-2">Docente titular</span>
                <span class="text-[13px] font-medium text-ink">{s.docente}</span>
              </div>
              <div class="flex flex-col">
                <span class="text-[11.5px] text-ink-2">Completud</span>
                <span class="text-[13px] font-medium text-ink">{s.completud}%</span>
              </div>
            </div>
          </section>

          <!-- Presentación -->
          {#if s.descripcion}
            <section id="sec-presentacion" class="flex flex-col gap-2">
              <div class="flex items-baseline gap-2">
                <span class="font-mono text-[12px] text-ink-2">{romano('presentacion')}</span>
                <h2 class="m-0 text-[16px] font-semibold text-ink">Presentación</h2>
              </div>
              <p class="m-0 text-pretty text-[13.5px] leading-relaxed text-ink">
                {s.descripcion}
              </p>
            </section>
          {/if}

          <!-- Objetivos -->
          {#if s.objetivos.length > 0}
            <section id="sec-objetivos" class="flex flex-col gap-2">
              <div class="flex items-baseline gap-2">
                <span class="font-mono text-[12px] text-ink-2">{romano('objetivos')}</span>
                <h2 class="m-0 text-[16px] font-semibold text-ink">Objetivos</h2>
              </div>
              <ul class="m-0 flex list-disc flex-col gap-1.5 pl-[18px] text-[13.5px] text-ink">
                {#each s.objetivos as obj, i (i)}
                  <li>{obj}</li>
                {/each}
              </ul>
            </section>
          {/if}

          <!-- Unidades -->
          {#if s.unidades.length > 0}
            <section id="sec-unidades" class="flex flex-col gap-2">
              <div class="flex items-baseline gap-2">
                <span class="font-mono text-[12px] text-ink-2">{romano('unidades')}</span>
                <h2 class="m-0 text-[16px] font-semibold text-ink">Unidades</h2>
              </div>
              <div class="overflow-hidden rounded-[10px] border border-gray-200">
                <table class="w-full border-collapse text-[13px]">
                  <thead class="bg-sand">
                    <tr>
                      <th
                        class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-2"
                        >Unidad</th
                      >
                      <th
                        class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-2"
                        >Resultado de aprendizaje</th
                      >
                      <th
                        class="w-[70px] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-2"
                        >Horas</th
                      >
                    </tr>
                  </thead>
                  <tbody>
                    {#each s.unidades as unidad, ui (ui)}
                      <tr class="border-t border-gray-200 align-top">
                        <td class="px-3 py-2.5 font-medium text-ink"
                          >{unidad.numero}. {unidad.titulo}</td
                        >
                        <td class="px-3 py-2.5 text-ink">
                          {#if unidad.contenidos.length > 0}
                            <ul class="m-0 flex list-disc flex-col gap-1 pl-4">
                              {#each unidad.contenidos as contenido, ci (ci)}
                                <li>{contenido}</li>
                              {/each}
                            </ul>
                          {:else}
                            <span class="text-gray-400">Sin resultados declarados</span>
                          {/if}
                        </td>
                        <td class="px-3 py-2.5 text-ink-2">
                          {unidad.horas > 0 ? unidad.horas : '—'}
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </section>
          {/if}

          <!-- Evaluación -->
          {#if s.evaluaciones.length > 0}
            <section id="sec-evaluacion" class="flex flex-col gap-2">
              <div class="flex items-baseline gap-2">
                <span class="font-mono text-[12px] text-ink-2">{romano('evaluacion')}</span>
                <h2 class="m-0 text-[16px] font-semibold text-ink">Evaluación</h2>
              </div>
              <div class="overflow-hidden rounded-[10px] border border-gray-200">
                <table class="w-full border-collapse text-[13px]">
                  <thead class="bg-sand">
                    <tr>
                      <th
                        class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-2"
                        >Evaluación</th
                      >
                      <th
                        class="w-[110px] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-2"
                        >Ponderación</th
                      >
                      <th
                        class="w-[90px] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-2"
                        >Semana</th
                      >
                    </tr>
                  </thead>
                  <tbody>
                    {#each s.evaluaciones as ev, i (i)}
                      <tr class="border-t border-gray-200">
                        <td class="px-3 py-2.5 text-ink">{ev.descripcion}</td>
                        <td class="px-3 py-2.5 font-mono text-ink">{ev.ponderacion}</td>
                        <td class="px-3 py-2.5 text-ink-2">
                          {ev.semana === '' || ev.semana === null ? '—' : `Sem. ${ev.semana}`}
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </section>
          {/if}
        {/if}
      </div>
    </div>

    <!-- ── Footer de decisión: fijo, sólo cuando el estado la admite ── -->
    {#if admiteDecision(curso) && slideOver.syllabus}
      <div
        class="flex flex-none items-center gap-3 border-t border-gray-200 bg-white px-5 py-3.5 shadow-[0_-1px_3px_rgba(0,0,0,.06)]"
      >
        <div class="flex min-w-0 flex-col">
          <span class="text-[12.5px] font-semibold text-ink">Decisión de jefatura</span>
          <span class="text-[12px] text-ink-2">Queda registrada con tu nombre y fecha</span>
        </div>
        <div class="ml-auto flex flex-none gap-2">
          <button onclick={abrirSolicitud} class={BTN_OUTLINE}>
            <MessageSquare class="h-[15px] w-[15px] text-ink-2" />
            Solicitar cambios
          </button>
          <button onclick={aprobarSyllabus} disabled={slideOver.approving} class={BTN_PRIMARY}>
            <Check class="h-[15px] w-[15px]" />
            {slideOver.approving ? 'Aprobando…' : 'Aprobar'}
          </button>
        </div>
      </div>
    {/if}
  {/if}
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Solicitar cambios. La razón es obligatoria: el campo nace en error y el
     botón nunca se dibuja gris — si falta, el envío no sale y el foco vuelve
     al textarea. Rojo porque la acción devuelve el syllabus al docente.
════════════════════════════════════════════════════════════════════════ -->

{#if solicitudAbierta && slideOver.curso}
  <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
  <div
    class="fixed inset-0 z-[60] flex items-center justify-center bg-[rgba(26,26,36,.45)] p-4"
    onclick={cerrarSolicitud}
    role="presentation"
  >
    <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
    <div
      class="w-full max-w-[720px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_20px_40px_rgba(0,0,0,.15)]"
      onclick={(e) => e.stopPropagation()}
      role="dialog"
      tabindex="-1"
      aria-modal="true"
      aria-label="Solicitar cambios"
    >
      <div
        class="flex flex-wrap items-center gap-2.5 border-b border-gray-200 bg-stone-50 px-5 py-3"
      >
        <MessageSquare class="h-[15px] w-[15px] text-ink-2" />
        <span class="text-[12.5px] font-semibold text-ink">
          Solicitar cambios — {slideOver.curso.cod_asignatura}
          {slideOver.curso.nombre_asignatura}
        </span>
        <span class="ml-auto text-[12px] text-ink-2">volverá a estado Rechazado</span>
      </div>

      <div class="flex flex-col gap-2 px-5 py-4">
        <label for="razon-solicitud" class="text-[13px] font-semibold text-ink">
          Razón de la solicitud <span class="text-red-600">*</span>
        </label>
        <textarea
          id="razon-solicitud"
          bind:this={razonInput}
          bind:value={razon}
          rows="3"
          maxlength={RAZON_MAX}
          placeholder="Indica qué debe corregir el docente antes de reenviar"
          aria-invalid={razonVacia}
          class="w-full resize-y rounded-lg border px-3 py-2.5 text-[14px] text-ink placeholder-gray-400 focus:outline-none {razonVacia
            ? 'border-red-600'
            : 'border-navy outline outline-2 outline-offset-1 outline-[rgba(0,47,108,.25)]'}"
        ></textarea>
        <div class="flex items-center gap-2.5">
          <span class="text-[12px] {razonVacia ? 'text-red-600' : 'text-ink-2'}">
            {razonError ??
              (razonVacia
                ? 'La razón es obligatoria: se envía al docente y queda en el historial.'
                : 'Se envía al docente y queda en el historial del syllabus.')}
          </span>
          <span class="ml-auto font-mono text-[11.5px] tabular-nums text-ink-2">
            {razon.length} / {RAZON_MAX}
          </span>
        </div>
      </div>

      <div class="flex items-center gap-3 border-t border-gray-200 px-5 py-3.5">
        <button
          onclick={cerrarSolicitud}
          class="rounded-lg px-3 py-2.5 text-[14px] font-medium text-navy transition-colors hover:bg-sand"
        >
          Volver al documento
        </button>
        <button
          onclick={enviarSolicitud}
          disabled={enviandoSolicitud}
          class="ml-auto inline-flex items-center gap-[7px] rounded-lg border border-red-600 bg-red-600 px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-red-700 disabled:opacity-60"
        >
          <Send class="h-[15px] w-[15px]" />
          {enviandoSolicitud ? 'Enviando…' : 'Enviar solicitud'}
        </button>
      </div>
    </div>
  </div>
{/if}
